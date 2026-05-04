# frozen_string_literal: true

require 'json'
require 'net/http'
require 'uri'

require_relative 'errors'
require_relative 'result'

module Walinko
  # Internal HTTP transport. Owns the wire format (JSON in/out, header
  # naming), retry policy, and error mapping. The `Messages` resource is
  # the only consumer.
  #
  # Public methods are documented but treat anything outside `request` as
  # internal — minor versions may refactor.
  class HttpClient
    Response = Struct.new(
      :status,
      :body,
      :request_id,
      :rate_limit,
      :idempotent_replayed,
      keyword_init: true
    )

    # Statuses we consider transient and retry automatically.
    RETRYABLE_HTTP_STATUSES = [429, 500, 502, 503, 504].freeze

    # Backoff curve (seconds). Index = attempt number (0 = first retry).
    # Caps at the last entry.
    BACKOFF_SECONDS = [0.25, 0.75, 1.75, 3.75].freeze

    # Cap on `Retry-After` honoring — don't let the server make us sleep
    # forever.
    MAX_RETRY_AFTER_SECONDS = 60

    attr_reader :last_request_id, :last_rate_limit

    # @param config [Walinko::Configuration]
    def initialize(config)
      @config = config
    end

    # Issues an HTTP request with the configured retry policy.
    #
    # @param method [Symbol] :get / :post
    # @param path   [String] e.g. "/api/v1/public/messages"
    # @param body   [Hash, nil] JSON-encoded into the request body
    # @param headers [Hash{String => String}] extra headers to merge
    # @return [Response]
    # @raise [Walinko::ApiError, Walinko::ConnectionError]
    def request(method:, path:, body: nil, headers: {})
      attempt = 0

      loop do
        result = perform(method: method, path: path, body: body, headers: headers)
        return result if result.is_a?(Response)

        # `result` is a retryable error (Exception subclass) — decide
        # whether to retry.
        attempt += 1
        raise result if attempt > @config.max_retries

        sleep_for = sleep_seconds(result, attempt)
        log(:warn,
            "retrying after #{sleep_for}s (attempt #{attempt}/#{@config.max_retries}): #{result.message}")
        sleep(sleep_for) if sleep_for.positive?
      end
    end

    private

    # Returns either a `Response` (success) or an Exception instance
    # representing a retryable failure. Non-retryable failures are
    # raised directly.
    def perform(method:, path:, body:, headers:)
      uri = URI.join("#{@config.base_url}/", path.sub(%r{^/}, ''))
      req = build_request(method, uri, body: body, headers: headers)

      http = Net::HTTP.new(uri.host, uri.port)
      http.use_ssl = (uri.scheme == 'https')
      http.read_timeout = @config.timeout
      http.open_timeout = @config.open_timeout
      http.write_timeout = @config.timeout if http.respond_to?(:write_timeout=)

      raw = http.request(req)
      handle_response(raw)
    rescue *connection_error_classes => e
      ConnectionError.new("#{e.class}: #{e.message}", cause_class: e.class)
    rescue ConnectionError => e
      e
    end

    # Network/timeout errors that should map to ConnectionError and be
    # retried.
    def connection_error_classes
      [
        Errno::ECONNREFUSED, Errno::ECONNRESET, Errno::EHOSTUNREACH,
        Errno::ENETUNREACH,  Errno::ETIMEDOUT,  Errno::EPIPE,
        SocketError, IOError,
        Net::OpenTimeout, Net::ReadTimeout, Net::WriteTimeout,
        OpenSSL::SSL::SSLError,
        EOFError
      ]
    end

    def build_request(method, uri, body:, headers:)
      request_class =
        case method
        when :get  then Net::HTTP::Get
        when :post then Net::HTTP::Post
        else raise ArgumentError, "Unsupported method #{method.inspect}"
        end

      req = request_class.new(uri.request_uri)
      req['Authorization'] = "Bearer #{@config.api_key}"
      req['Accept']        = 'application/json'
      headers.each { |k, v| req[k] = v unless v.nil? }
      if body
        req['Content-Type'] = 'application/json'
        req.body = JSON.generate(body)
      end
      req
    end

    def handle_response(raw)
      status = raw.code.to_i
      request_id = raw['X-Request-Id'] || raw['x-request-id']
      rate_limit = parse_rate_limit(raw)
      replayed   = raw['Idempotent-Replayed'] == 'true'

      @last_request_id = request_id
      @last_rate_limit = rate_limit

      parsed = safe_parse_json(raw.body)

      if (200..299).cover?(status)
        return Response.new(
          status: status,
          body: parsed,
          request_id: request_id,
          rate_limit: rate_limit,
          idempotent_replayed: replayed
        )
      end

      build_api_error(status, parsed, raw)
    end

    # Builds the typed exception. For retryable statuses returns the
    # exception (caller decides whether to retry); for non-retryable
    # statuses raises immediately.
    def build_api_error(status, body, raw)
      message    = (body.is_a?(Hash) && body['message']) || "HTTP #{status}"
      error_code = body.is_a?(Hash) ? body['error_code'] : nil
      details    = body.is_a?(Hash) ? body['details']    : nil
      request_id = raw['X-Request-Id'] || raw['x-request-id']

      klass = ErrorMapping.for(http_status: status, error_code: error_code)

      err =
        if klass <= RateLimitError
          retry_after = parse_retry_after(raw)
          klass.new(message,
                    http_status: status, error_code: error_code,
                    request_id: request_id, details: stringify_keys(details),
                    body: body, retry_after: retry_after)
        else
          klass.new(message,
                    http_status: status, error_code: error_code,
                    request_id: request_id, details: stringify_keys(details),
                    body: body)
        end

      return err if RETRYABLE_HTTP_STATUSES.include?(status)

      raise err
    end

    def parse_rate_limit(raw)
      limit_h     = raw['X-RateLimit-Limit']
      remaining_h = raw['X-RateLimit-Remaining']
      return nil if limit_h.nil? && remaining_h.nil?

      RateLimitSnapshot.new(
        limit: limit_h&.to_i,
        remaining: remaining_h&.to_i
      )
    end

    def parse_retry_after(raw)
      v = raw['Retry-After']
      return nil if v.nil?
      return nil unless v.match?(/\A\d+\z/)

      v.to_i
    end

    def safe_parse_json(body)
      return nil if body.nil? || body.empty?

      JSON.parse(body)
    rescue JSON::ParserError
      nil
    end

    def stringify_keys(hash)
      return {} unless hash.is_a?(Hash)

      hash.each_with_object({}) { |(k, v), out| out[k.to_s] = v }
    end

    def sleep_seconds(error, attempt)
      if error.is_a?(RateLimitError) && error.retry_after
        return [error.retry_after, MAX_RETRY_AFTER_SECONDS].min
      end

      base = BACKOFF_SECONDS[[attempt - 1, BACKOFF_SECONDS.size - 1].min]
      base + (rand * 0.1) # tiny jitter
    end

    def log(level, message)
      logger = @config.logger
      return unless logger.respond_to?(level)

      logger.public_send(level, "[walinko] #{message}")
    end
  end
end
