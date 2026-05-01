# frozen_string_literal: true

require 'spec_helper'

RSpec.describe Walinko::ErrorMapping do
  describe '.for' do
    it 'maps 401 to AuthenticationError' do
      expect(described_class.for(http_status: 401)).to eq(Walinko::AuthenticationError)
    end

    it 'maps 400 to BadRequestError' do
      expect(described_class.for(http_status: 400)).to eq(Walinko::BadRequestError)
    end

    it 'maps 404 to NotFoundError' do
      expect(described_class.for(http_status: 404)).to eq(Walinko::NotFoundError)
    end

    it 'maps 422 to ValidationError' do
      expect(described_class.for(http_status: 422)).to eq(Walinko::ValidationError)
    end

    it 'maps 429 to RateLimitError' do
      expect(described_class.for(http_status: 429)).to eq(Walinko::RateLimitError)
    end

    it 'maps 504 to TimeoutError' do
      expect(described_class.for(http_status: 504)).to eq(Walinko::TimeoutError)
    end

    it 'maps generic 5xx to ServerError' do
      expect(described_class.for(http_status: 500)).to eq(Walinko::ServerError)
      expect(described_class.for(http_status: 503)).to eq(Walinko::ServerError)
    end

    it 'specializes 403 + tenant_suspended' do
      expect(
        described_class.for(http_status: 403, error_code: 'tenant_suspended')
      ).to eq(Walinko::TenantSuspendedError)
    end

    it 'specializes 403 + quota_exceeded' do
      expect(
        described_class.for(http_status: 403, error_code: 'quota_exceeded')
      ).to eq(Walinko::QuotaExceededError)
    end

    it 'falls back to ForbiddenError for plain 403' do
      expect(described_class.for(http_status: 403)).to eq(Walinko::ForbiddenError)
    end

    it 'specializes 409 + device_disconnected' do
      expect(
        described_class.for(http_status: 409, error_code: 'device_disconnected')
      ).to eq(Walinko::DeviceDisconnectedError)
    end

    it 'specializes 409 + idempotency_conflict' do
      expect(
        described_class.for(http_status: 409, error_code: 'idempotency_conflict')
      ).to eq(Walinko::IdempotencyConflictError)
    end

    it 'falls back to ConflictError for plain 409' do
      expect(described_class.for(http_status: 409)).to eq(Walinko::ConflictError)
    end

    it 'falls back to ApiError for unknown statuses' do
      expect(described_class.for(http_status: 418)).to eq(Walinko::ApiError)
    end
  end
end

RSpec.describe Walinko::ValidationError do
  it 'exposes #fields when details has a fields hash' do
    err = described_class.new(
      'Validation failed',
      http_status: 422,
      error_code: 'validation_error',
      details: { 'fields' => { 'phone' => ['must not be empty'] } }
    )
    expect(err.fields).to eq('phone' => ['must not be empty'])
  end

  it 'returns an empty hash when details is missing' do
    err = described_class.new('boom', http_status: 422)
    expect(err.fields).to eq({})
  end
end

RSpec.describe Walinko::RateLimitError do
  it 'exposes retry_after seconds' do
    err = described_class.new('limited', http_status: 429, retry_after: 12)
    expect(err.retry_after).to eq(12)
  end
end

RSpec.describe Walinko::ApiError do
  it 'has a debuggable inspect' do
    err = Walinko::AuthenticationError.new(
      'bad key',
      http_status: 401,
      error_code: 'invalid_api_key',
      request_id: 'req_abc'
    )
    expect(err.inspect).to include('Walinko::AuthenticationError', 'status=401',
                                   'code=invalid_api_key', 'request_id=req_abc')
  end
end

RSpec.describe Walinko::ConnectionError do
  it 'is a Walinko::Error' do
    expect(described_class.ancestors).to include(Walinko::Error)
  end
end
