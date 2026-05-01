# frozen_string_literal: true

require 'securerandom'

require_relative 'errors'
require_relative 'http_client'
require_relative 'result'

module Walinko
  # The `messages` resource on `Walinko::Client`. Wraps:
  #
  #   POST /api/v1/public/messages
  #   GET  /api/v1/public/messages/:tracking_id
  class Messages
    SEND_PATH = '/api/v1/public/messages'

    # @api private
    def initialize(http_client)
      @http = http_client
    end

    # Synchronous send: blocks until the WhatsApp gateway acknowledges
    # delivery (or the server's 15s timeout fires).
    #
    # @param device_id      [Integer] required
    # @param template_id    [Integer] required
    # @param phone          [String]  required, E.164 (`+8801617738431`)
    # @param variables      [Hash]    optional `{ name: 'value' }` map
    # @param variant_index  [Integer] optional, 0 = primary
    # @param idempotency_key [String] optional; auto-generated otherwise
    # @return [Walinko::SyncResult]
    def send(device_id:, template_id:, phone:,
             variables: nil, variant_index: nil,
             idempotency_key: nil)
      payload = build_payload(
        device_id: device_id, template_id: template_id,
        phone: phone, variables: variables,
        variant_index: variant_index, async: false
      )

      response = post(payload, idempotency_key: idempotency_key)
      data = extract_data(response.body)

      SyncResult.new(
        data: data,
        request_id: response.request_id,
        rate_limit: response.rate_limit,
        idempotent_replayed: response.idempotent_replayed
      )
    end

    # Asynchronous enqueue: server returns immediately with a tracking id;
    # the actual WhatsApp send happens out-of-band. Poll `fetch` (or use
    # `wait_until_done`) for the final state.
    #
    # @return [Walinko::AsyncJob]
    def enqueue(device_id:, template_id:, phone:,
                variables: nil, variant_index: nil,
                idempotency_key: nil)
      payload = build_payload(
        device_id: device_id, template_id: template_id,
        phone: phone, variables: variables,
        variant_index: variant_index, async: true
      )

      response = post(payload, idempotency_key: idempotency_key)
      data = extract_data(response.body)

      AsyncJob.new(
        data: data,
        request_id: response.request_id,
        rate_limit: response.rate_limit,
        idempotent_replayed: response.idempotent_replayed
      )
    end

    # Look up a delivery by its tracking id.
    #
    # @param tracking_id [String] e.g. `tx_767fd2faca0f4037b2a2bbcb91e5735f`
    # @return [Walinko::MessageStatus]
    def fetch(tracking_id)
      raise ArgumentError, 'tracking_id is required' if tracking_id.nil? || tracking_id.to_s.empty?

      response = @http.request(method: :get, path: "#{SEND_PATH}/#{tracking_id}")
      data = extract_data(response.body)

      MessageStatus.new(data: data, request_id: response.request_id)
    end

    # Poll `fetch` until the delivery reaches a terminal state (sent /
    # failed) or the timeout expires.
    #
    # @param tracking_id [String]
    # @param timeout  [Integer] max seconds to wait, default 60
    # @param interval [Numeric] seconds between polls, default 2
    # @return [Walinko::MessageStatus]
    # @raise  [Walinko::TimeoutError] if the message is still pending
    #         when `timeout` elapses (the message is *not* failed —
    #         continue polling later if you need to)
    def wait_until_done(tracking_id, timeout: 60, interval: 2)
      raise ArgumentError, 'timeout must be > 0'  if timeout.to_i  <= 0
      raise ArgumentError, 'interval must be > 0' if interval.to_f <= 0

      deadline = monotonic_now + timeout.to_f
      loop do
        status = fetch(tracking_id)
        return status if status.done?

        if monotonic_now + interval.to_f >= deadline
          raise TimeoutError.new(
            "Timed out waiting for #{tracking_id} after #{timeout}s (still #{status.status})",
            http_status: 504,
            error_code: 'send_timeout',
            request_id: status.request_id,
            details: { 'last_status' => status.status }
          )
        end

        sleep(interval.to_f)
      end
    end

    private

    def post(payload, idempotency_key:)
      key = idempotency_key&.to_s
      key = generate_idempotency_key if key.nil? || key.empty?

      @http.request(
        method: :post,
        path: SEND_PATH,
        body: payload,
        headers: { 'Idempotency-Key' => key }
      )
    end

    def build_payload(device_id:, template_id:, phone:,
                      variables:, variant_index:, async:)
      raise ArgumentError, 'device_id is required'   if device_id.nil?
      raise ArgumentError, 'template_id is required' if template_id.nil?
      raise ArgumentError, 'phone is required'       if phone.nil? || phone.to_s.empty?

      payload = {
        'device_id' => Integer(device_id),
        'template_id' => Integer(template_id),
        'phone' => phone.to_s,
        'async' => async
      }
      payload['variant_index'] = Integer(variant_index) unless variant_index.nil?
      payload['variables']     = stringify_variables(variables) if variables
      payload
    end

    def stringify_variables(variables)
      raise ArgumentError, 'variables must be a Hash' unless variables.is_a?(Hash)

      variables.each_with_object({}) do |(k, v), out|
        out[k.to_s] = v.nil? ? '' : v.to_s
      end
    end

    def extract_data(body)
      return {} unless body.is_a?(Hash)

      data = body['data']
      data.is_a?(Hash) ? data : {}
    end

    def generate_idempotency_key
      "walinko-rb-#{SecureRandom.uuid}"
    end

    def monotonic_now
      Process.clock_gettime(Process::CLOCK_MONOTONIC)
    end
  end
end
