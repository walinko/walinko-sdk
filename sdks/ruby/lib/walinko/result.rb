# frozen_string_literal: true

require 'time'

module Walinko
  # Returned by `client.messages.send(...)` (sync mode). Wraps the
  # `data` block of a 200 OK response.
  class SyncResult
    attr_reader :tracking_id, :status, :device_id, :template_id,
                :variant_index, :phone, :sent_at, :wa_message_id,
                :request_id, :rate_limit, :idempotent_replayed

    def initialize(data:, request_id:, rate_limit:, idempotent_replayed:)
      @tracking_id         = data['tracking_id']
      @status              = data['status']
      @device_id           = data['device_id']
      @template_id         = data['template_id']
      @variant_index       = data['variant_index']
      @phone               = data['phone']
      @sent_at             = parse_time(data['sent_at'])
      @wa_message_id       = data['wa_message_id']
      @request_id          = request_id
      @rate_limit          = rate_limit
      @idempotent_replayed = idempotent_replayed
    end

    def sent?
      status == 'sent'
    end

    private

    def parse_time(value)
      return nil if value.nil? || value.to_s.empty?

      Time.iso8601(value)
    rescue ArgumentError
      nil
    end
  end

  # Returned by `client.messages.enqueue(...)` (async mode). Wraps the
  # `data` block of a 202 Accepted response.
  class AsyncJob
    attr_reader :tracking_id, :status, :status_url,
                :request_id, :rate_limit, :idempotent_replayed

    def initialize(data:, request_id:, rate_limit:, idempotent_replayed:)
      @tracking_id         = data['tracking_id']
      @status              = data['status']
      @status_url          = data['status_url']
      @request_id          = request_id
      @rate_limit          = rate_limit
      @idempotent_replayed = idempotent_replayed
    end

    def queued?
      status == 'queued'
    end
  end

  # Returned by `client.messages.fetch(tracking_id)`. Wraps the `data`
  # block of `GET /messages/:trackingId`.
  class MessageStatus
    attr_reader :tracking_id, :status, :device_id, :template_id,
                :variant_index, :phone, :wa_message_id,
                :error_code, :error_message,
                :sent_at, :created_at,
                :request_id

    def initialize(data:, request_id:)
      @tracking_id   = data['tracking_id']
      @status        = data['status']
      @device_id     = data['device_id']
      @template_id   = data['template_id']
      @variant_index = data['variant_index']
      @phone         = data['phone']
      @wa_message_id = data['wa_message_id']
      @error_code    = data['error_code']
      @error_message = data['error_message']
      @sent_at       = parse_time(data['sent_at'])
      @created_at    = parse_time(data['created_at'])
      @request_id    = request_id
    end

    def sent?;    status == 'sent';   end
    def failed?;  status == 'failed'; end
    def pending?; %w[queued sending].include?(status); end
    def done?;    sent? || failed?; end

    private

    def parse_time(value)
      return nil if value.nil? || value.to_s.empty?

      Time.iso8601(value)
    rescue ArgumentError
      nil
    end
  end

  # Snapshot of the server-reported rate-limit window from the most
  # recent response.
  class RateLimitSnapshot
    attr_reader :limit, :remaining

    def initialize(limit:, remaining:)
      @limit     = limit
      @remaining = remaining
    end

    def saturated?
      remaining.is_a?(Integer) && remaining <= 0
    end
  end
end
