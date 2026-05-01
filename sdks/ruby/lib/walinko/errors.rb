# frozen_string_literal: true

module Walinko
  # Base class for every exception raised by the SDK. Catch this to handle
  # anything Walinko throws.
  class Error < StandardError; end

  # Raised when we couldn't reach the API at all (DNS failure, connection
  # refused, TLS handshake failed, socket reset, etc.). These are retried
  # automatically up to `max_retries`; if you see one, the retry budget was
  # exhausted.
  class ConnectionError < Error
    attr_reader :cause_class

    def initialize(message, cause_class: nil)
      super(message)
      @cause_class = cause_class
    end
  end

  # Base class for every error response returned by the API. Carries the
  # full diagnostic payload — HTTP status, server-side `error_code`, the
  # `X-Request-Id` (handy for support tickets), and the optional `details`
  # blob.
  class ApiError < Error
    attr_reader :http_status, :error_code, :request_id, :details, :body

    def initialize(message, http_status:, error_code: nil, request_id: nil,
                   details: nil, body: nil)
      super(message)
      @http_status = http_status
      @error_code  = error_code
      @request_id  = request_id
      @details     = details || {}
      @body        = body
    end

    def inspect
      parts = [self.class.name, "status=#{http_status}"]
      parts << "code=#{error_code}" if error_code
      parts << "request_id=#{request_id}" if request_id
      "#<#{parts.join(' ')} message=#{message.inspect}>"
    end
  end

  # 401 — missing / malformed / expired / revoked / unknown API key.
  # All five are returned with the same generic message on purpose.
  class AuthenticationError < ApiError; end

  # 400 — malformed request, unknown body shape, or `variant_index` out of
  # range. Validation errors are raised as `ValidationError` instead.
  class BadRequestError < ApiError; end

  # 403 — generic forbidden. See specialized subclasses below.
  class ForbiddenError < ApiError; end

  # 403 + `error_code: tenant_suspended` — tenant account is suspended or
  # scheduled for deletion.
  class TenantSuspendedError < ForbiddenError; end

  # 403 + `error_code: quota_exceeded` — tenant has hit its monthly /
  # daily / balance message limit. `details[:resets_at]` (when present)
  # tells you when the limit resets.
  class QuotaExceededError < ForbiddenError; end

  # 404 — device, template, or tracking id not found for this tenant.
  class NotFoundError < ApiError; end

  # 409 — generic conflict. See specialized subclasses below.
  class ConflictError < ApiError; end

  # 409 + `error_code: device_disconnected` — device session is not
  # connected. Reconnect from the dashboard, then retry.
  class DeviceDisconnectedError < ConflictError; end

  # 409 + `error_code: idempotency_conflict` — `Idempotency-Key` was
  # previously used with a *different* payload. Use a new key.
  class IdempotencyConflictError < ConflictError; end

  # 422 — semantic validation failure. For DTO validation errors (the
  # default class-validator path), `#fields` returns a `{field => [reason,
  # ...]}` map. For `phone_not_on_whatsapp` the map is empty and the
  # reason is in `#message`.
  class ValidationError < ApiError
    # @return [Hash{String => Array<String>}]
    def fields
      f = details[:fields] || details['fields']
      f.is_a?(Hash) ? f : {}
    end
  end

  # 429 — sliding-window rate limit (default 30 req/min/key) exceeded.
  # `#retry_after` is the recommended sleep in seconds (parsed from
  # `Retry-After` header).
  class RateLimitError < ApiError
    attr_reader :retry_after

    def initialize(message, retry_after: nil, **kwargs)
      super(message, **kwargs)
      @retry_after = retry_after
    end
  end

  # 5xx — server-side failure. Outcome of the underlying send may or may
  # not have happened; replay with the same `Idempotency-Key` is safe.
  class ServerError < ApiError; end

  # 504 — WhatsApp send did not complete within the server's 15s window.
  # Outcome unknown; replay with the same `Idempotency-Key` is safe.
  class TimeoutError < ApiError; end

  # Internal: maps an HTTP status + server `error_code` to one of the
  # typed exception classes above. Public so users can introspect the
  # mapping in tests if they want.
  module ErrorMapping
    BY_HTTP_STATUS = {
      400 => BadRequestError,
      401 => AuthenticationError,
      403 => ForbiddenError,
      404 => NotFoundError,
      409 => ConflictError,
      422 => ValidationError,
      429 => RateLimitError,
      504 => TimeoutError
    }.freeze

    BY_ERROR_CODE = {
      'tenant_suspended' => TenantSuspendedError,
      'quota_exceeded' => QuotaExceededError,
      'device_disconnected' => DeviceDisconnectedError,
      'idempotency_conflict' => IdempotencyConflictError
    }.freeze

    # @return [Class<ApiError>]
    def self.for(http_status:, error_code: nil)
      BY_ERROR_CODE[error_code.to_s] ||
        BY_HTTP_STATUS[http_status] ||
        (http_status.between?(500, 599) ? ServerError : ApiError)
    end
  end
end
