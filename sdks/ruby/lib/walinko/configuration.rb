# frozen_string_literal: true

module Walinko
  # Immutable configuration captured by `Walinko::Client.new`. Validates
  # required fields and applies defaults.
  class Configuration
    DEFAULT_BASE_URL     = 'https://api.walinko.com'
    DEFAULT_TIMEOUT      = 30
    DEFAULT_OPEN_TIMEOUT = 10
    DEFAULT_MAX_RETRIES  = 2

    attr_reader :api_key, :base_url, :timeout, :open_timeout,
                :max_retries, :logger

    # @param api_key      [String]   required, e.g. "walk_live_<keyId>.<secret>"
    # @param base_url     [String]   defaults to "https://api.walinko.com"
    # @param timeout      [Integer]  per-request read timeout in seconds
    # @param open_timeout [Integer]  TCP/TLS connection-setup timeout in seconds
    # @param max_retries  [Integer]  retries on idempotent failures (network, 429, 5xx)
    # @param logger       [#info,#warn,#error] optional structured logger
    def initialize(api_key:,
                   base_url:     DEFAULT_BASE_URL,
                   timeout:      DEFAULT_TIMEOUT,
                   open_timeout: DEFAULT_OPEN_TIMEOUT,
                   max_retries:  DEFAULT_MAX_RETRIES,
                   logger:       nil)
      raise ArgumentError, 'api_key is required' if api_key.nil? || api_key.to_s.empty?
      raise ArgumentError, 'base_url is required' if base_url.nil? || base_url.to_s.empty?
      raise ArgumentError, 'timeout must be > 0' if timeout.to_i <= 0
      raise ArgumentError, 'open_timeout must be > 0' if open_timeout.to_i <= 0
      raise ArgumentError, 'max_retries must be >= 0' if max_retries.to_i.negative?

      @api_key      = api_key.to_s
      @base_url     = base_url.to_s.sub(%r{/+$}, '')
      @timeout      = timeout.to_i
      @open_timeout = open_timeout.to_i
      @max_retries  = max_retries.to_i
      @logger       = logger
    end
  end
end
