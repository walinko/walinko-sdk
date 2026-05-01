# frozen_string_literal: true

require_relative 'walinko/version'
require_relative 'walinko/errors'
require_relative 'walinko/configuration'
require_relative 'walinko/result'
require_relative 'walinko/http_client'
require_relative 'walinko/messages'

# Walinko Ruby SDK — server-to-server client for the Walinko public API.
#
# See the README for a quick-start; the contract is pinned in
# `walinko-sdk/docs/openapi.yaml`.
#
# TODO(walinko-webhooks): when the server starts emitting webhooks,
# `Walinko::Client#webhooks` (e.g. `client.webhooks.verify(payload, sig)`)
# will land here. Reserving the namespace so v1 callers don't break.
module Walinko
  class Client
    # @return [Walinko::Configuration]
    attr_reader :config

    # @return [Walinko::Messages]
    attr_reader :messages

    # @param api_key      [String] required, e.g. "walk_live_<keyId>.<secret>"
    # @param base_url     [String] optional, defaults to "https://api.walinko.com"
    # @param timeout      [Integer] read timeout in seconds (default 30)
    # @param open_timeout [Integer] connection timeout in seconds (default 10)
    # @param max_retries  [Integer] retries on idempotent failures (default 2)
    # @param logger       [#info,#warn,#error] optional structured logger
    def initialize(api_key:, **opts)
      @config = Configuration.new(api_key: api_key, **opts)
      @http   = HttpClient.new(@config)
      @messages = Messages.new(@http)
    end

    # Snapshot of the rate-limit window from the most recent response.
    # Returns `nil` until the first call has completed.
    # @return [Walinko::RateLimitSnapshot, nil]
    def last_rate_limit
      @http.last_rate_limit
    end

    # The `X-Request-Id` from the most recent response (or `nil`).
    # Useful when filing support tickets.
    # @return [String, nil]
    def last_request_id
      @http.last_request_id
    end
  end
end
