# frozen_string_literal: true

require 'spec_helper'

RSpec.describe Walinko::Messages do
  let(:base_url) { 'https://api.example.com' }
  let(:api_key)  { 'walk_live_keyid.secret' }

  let(:client) do
    Walinko::Client.new(
      api_key: api_key,
      base_url: base_url,
      timeout: 5,
      open_timeout: 5,
      max_retries: 0 # disable retries by default; opt in per-test
    )
  end

  let(:send_url)   { "#{base_url}/api/v1/public/messages" }
  let(:status_url) { "#{base_url}/api/v1/public/messages/tx_abc" }

  # ---- helpers --------------------------------------------------------

  def success_envelope(data)
    JSON.generate('success' => true, 'message' => 'ok', 'data' => data)
  end

  def error_envelope(message, error_code: nil, details: nil)
    body = { 'success' => false, 'message' => message }
    body['error_code'] = error_code if error_code
    body['details']    = details    if details
    JSON.generate(body)
  end

  def common_response_headers(extra = {})
    {
      'Content-Type' => 'application/json',
      'X-Request-Id' => 'req_abc123',
      'X-RateLimit-Limit' => '30',
      'X-RateLimit-Remaining' => '29'
    }.merge(extra)
  end

  # ---- sync send ------------------------------------------------------

  describe '#send (sync)' do
    let(:sync_data) do
      {
        'tracking_id' => 'tx_abc',
        'status' => 'sent',
        'device_id' => 1,
        'template_id' => 12,
        'variant_index' => 0,
        'phone' => '+8801617738431',
        'sent_at' => '2026-05-01T10:00:00Z',
        'wa_message_id' => 'wamid.HBgL...'
      }
    end

    it 'POSTs JSON, returns a SyncResult, and exposes metadata on the client' do
      stub = stub_request(:post, send_url)
             .with(
               headers: {
                 'Authorization' => "Bearer #{api_key}",
                 'Content-Type' => 'application/json',
                 'Accept' => 'application/json'
               }
             ) do |req|
        body = JSON.parse(req.body)
        expect(body).to include(
          'device_id' => 1, 'template_id' => 12, 'phone' => '+8801617738431', 'async' => false
        )
        expect(body['variables']).to eq('name' => 'Kazi')
        expect(req.headers['Idempotency-Key']).to start_with('walinko-rb-')
        true
      end
        .to_return(status: 200, body: success_envelope(sync_data),
                   headers: common_response_headers)

      result = client.messages.send(
        device_id: 1, template_id: 12, phone: '+8801617738431',
        variables: { name: 'Kazi' }
      )

      expect(stub).to have_been_requested
      expect(result).to be_a(Walinko::SyncResult)
      expect(result.tracking_id).to eq('tx_abc')
      expect(result.sent?).to be true
      expect(result.sent_at).to be_a(Time)
      expect(result.wa_message_id).to eq('wamid.HBgL...')
      expect(result.request_id).to eq('req_abc123')
      expect(result.idempotent_replayed).to be false

      expect(client.last_request_id).to eq('req_abc123')
      expect(client.last_rate_limit).to be_a(Walinko::RateLimitSnapshot)
      expect(client.last_rate_limit.limit).to eq(30)
      expect(client.last_rate_limit.remaining).to eq(29)
    end

    it 'uses a caller-provided Idempotency-Key when present' do
      stub = stub_request(:post, send_url)
             .with(headers: { 'Idempotency-Key' => 'order-123' })
             .to_return(status: 200, body: success_envelope(sync_data),
                        headers: common_response_headers)

      client.messages.send(
        device_id: 1, template_id: 12, phone: '+8801617738431',
        idempotency_key: 'order-123'
      )

      expect(stub).to have_been_requested
    end

    it 'reports idempotent_replayed when server flags it' do
      stub_request(:post, send_url).to_return(
        status: 200,
        body: success_envelope(sync_data),
        headers: common_response_headers('Idempotent-Replayed' => 'true')
      )

      result = client.messages.send(
        device_id: 1, template_id: 12, phone: '+8801617738431'
      )
      expect(result.idempotent_replayed).to be true
    end

    it 'stringifies variable values and treats nil as empty string' do
      stub = stub_request(:post, send_url) do |req|
        body = JSON.parse(req.body)
        expect(body['variables']).to eq('age' => '7', 'note' => '')
        true
      end.to_return(status: 200, body: success_envelope(sync_data),
                    headers: common_response_headers)

      client.messages.send(
        device_id: 1, template_id: 12, phone: '+8801617738431',
        variables: { age: 7, note: nil }
      )

      expect(stub).to have_been_requested
    end

    it 'omits variant_index when nil' do
      stub = stub_request(:post, send_url) do |req|
        body = JSON.parse(req.body)
        expect(body).not_to have_key('variant_index')
        true
      end.to_return(status: 200, body: success_envelope(sync_data),
                    headers: common_response_headers)

      client.messages.send(
        device_id: 1, template_id: 12, phone: '+8801617738431'
      )

      expect(stub).to have_been_requested
    end

    it 'rejects empty phone before any HTTP traffic' do
      expect do
        client.messages.send(device_id: 1, template_id: 1, phone: '')
      end.to raise_error(ArgumentError, /phone/)
    end
  end

  # ---- async enqueue --------------------------------------------------

  describe '#enqueue (async)' do
    let(:async_data) do
      {
        'tracking_id' => 'tx_async',
        'status' => 'queued',
        'status_url' => 'https://api.example.com/api/v1/public/messages/tx_async'
      }
    end

    it 'POSTs with async: true and returns an AsyncJob' do
      stub = stub_request(:post, send_url) do |req|
        body = JSON.parse(req.body)
        expect(body['async']).to be true
        true
      end.to_return(status: 202, body: success_envelope(async_data),
                    headers: common_response_headers)

      job = client.messages.enqueue(
        device_id: 1, template_id: 12, phone: '+8801617738431'
      )

      expect(stub).to have_been_requested
      expect(job).to be_a(Walinko::AsyncJob)
      expect(job.tracking_id).to eq('tx_async')
      expect(job.queued?).to be true
      expect(job.status_url).to include('tx_async')
    end
  end

  # ---- fetch / wait_until_done ---------------------------------------

  describe '#fetch' do
    it 'GETs by tracking_id and returns a MessageStatus' do
      stub_request(:get, status_url).to_return(
        status: 200,
        body: success_envelope(
          'tracking_id' => 'tx_abc',
          'status' => 'sent',
          'device_id' => 1,
          'template_id' => 12,
          'variant_index' => 0,
          'phone' => '+8801617738431',
          'wa_message_id' => 'wamid.HBgL...',
          'error_code' => nil,
          'error_message' => nil,
          'sent_at' => '2026-05-01T10:01:00Z',
          'created_at' => '2026-05-01T10:00:55Z'
        ),
        headers: common_response_headers
      )

      status = client.messages.fetch('tx_abc')
      expect(status).to be_a(Walinko::MessageStatus)
      expect(status.sent?).to be true
      expect(status.done?).to be true
      expect(status.created_at).to be_a(Time)
      expect(status.request_id).to eq('req_abc123')
    end

    it 'rejects nil tracking_id' do
      expect { client.messages.fetch(nil) }.to raise_error(ArgumentError)
    end
  end

  describe '#wait_until_done' do
    it 'returns the first terminal status' do
      stub_request(:get, status_url)
        .to_return(
          { status: 200, body: success_envelope('tracking_id' => 'tx_abc', 'status' => 'queued'),
            headers: common_response_headers },
          { status: 200, body: success_envelope('tracking_id' => 'tx_abc', 'status' => 'sending'),
            headers: common_response_headers },
          { status: 200,
            body: success_envelope('tracking_id' => 'tx_abc', 'status' => 'sent',
                                   'sent_at' => '2026-05-01T10:00:00Z'),
            headers: common_response_headers }
        )

      messages_resource = client.messages
      allow(messages_resource).to receive(:sleep)

      status = messages_resource.wait_until_done('tx_abc', timeout: 30, interval: 0.01)
      expect(status.sent?).to be true
    end

    it 'raises Walinko::TimeoutError when the deadline expires while still pending' do
      call_count = 0
      stub_request(:get, status_url).to_return do |_req|
        call_count += 1
        { status: 200,
          body: success_envelope('tracking_id' => 'tx_abc', 'status' => 'queued'),
          headers: common_response_headers }
      end

      messages_resource = client.messages
      allow(messages_resource).to receive(:sleep) do |sec|
        # Simulate elapsed time by advancing past the deadline after
        # a few polls so the timeout fires naturally.
        allow(messages_resource).to receive(:monotonic_now).and_return(
          Process.clock_gettime(Process::CLOCK_MONOTONIC) + 999
        )
      end

      expect do
        messages_resource.wait_until_done('tx_abc', timeout: 2, interval: 0.5)
      end.to raise_error(Walinko::TimeoutError, /tx_abc/)

      # Must have polled at least twice before timing out (proves the
      # deadline check no longer fires prematurely on the first poll).
      expect(call_count).to be >= 2
    end
  end

  # ---- error mapping (per error_code) --------------------------------

  describe 'error mapping' do
    {
      [401, 'invalid_api_key'] => Walinko::AuthenticationError,
      [400, 'bad_request'] => Walinko::BadRequestError,
      [400, 'variant_out_of_range'] => Walinko::BadRequestError,
      [403, 'tenant_suspended'] => Walinko::TenantSuspendedError,
      [403, 'quota_exceeded'] => Walinko::QuotaExceededError,
      [404, 'device_not_found'] => Walinko::NotFoundError,
      [404, 'template_not_found'] => Walinko::NotFoundError,
      [404, 'delivery_not_found'] => Walinko::NotFoundError,
      [409, 'device_disconnected'] => Walinko::DeviceDisconnectedError,
      [409, 'idempotency_conflict'] => Walinko::IdempotencyConflictError,
      [422, 'phone_not_on_whatsapp'] => Walinko::ValidationError,
      [422, 'validation_error'] => Walinko::ValidationError,
      [500, 'send_failed'] => Walinko::ServerError,
      [500, 'queue_failed'] => Walinko::ServerError,
      [500, 'internal_error'] => Walinko::ServerError,
      [504, 'send_timeout'] => Walinko::TimeoutError
    }.each do |(status, code), klass|
      it "raises #{klass} for #{status}/#{code}" do
        stub_request(:post, send_url).to_return(
          status: status,
          body: error_envelope("oh no: #{code}", error_code: code),
          headers: common_response_headers
        )

        expect do
          client.messages.send(device_id: 1, template_id: 1, phone: '+8801617738431')
        end.to raise_error(klass) do |err|
          expect(err.http_status).to eq(status)
          expect(err.error_code).to eq(code)
          expect(err.request_id).to eq('req_abc123')
        end
      end
    end

    it 'parses validation_error details.fields onto ValidationError#fields' do
      stub_request(:post, send_url).to_return(
        status: 422,
        body: error_envelope('Validation failed',
                             error_code: 'validation_error',
                             details: { 'fields' => { 'phone' => ['must not be empty'] } }),
        headers: common_response_headers
      )

      expect do
        client.messages.send(device_id: 1, template_id: 1, phone: '+8801617738431')
      end.to raise_error(Walinko::ValidationError) do |err|
        expect(err.fields).to eq('phone' => ['must not be empty'])
      end
    end

    it 'parses Retry-After onto RateLimitError#retry_after' do
      stub_request(:post, send_url).to_return(
        status: 429,
        body: error_envelope('rate limited', error_code: 'rate_limited'),
        headers: common_response_headers('Retry-After' => '5')
      )

      expect do
        client.messages.send(device_id: 1, template_id: 1, phone: '+8801617738431')
      end.to raise_error(Walinko::RateLimitError) do |err|
        expect(err.retry_after).to eq(5)
      end
    end
  end

  # ---- retry policy --------------------------------------------------

  describe 'retry policy' do
    let(:retry_client) do
      Walinko::Client.new(
        api_key: api_key, base_url: base_url,
        timeout: 5, open_timeout: 5, max_retries: 2
      )
    end

    before do
      # Skip real backoff sleeps.
      allow_any_instance_of(Walinko::HttpClient).to receive(:sleep)
    end

    it 'retries 5xx responses up to max_retries with the same Idempotency-Key' do
      seen_keys = []
      counter = 0

      stub_request(:post, send_url).to_return do |req|
        seen_keys << req.headers['Idempotency-Key']
        counter += 1
        if counter < 3
          { status: 503, body: error_envelope('boom', error_code: 'send_failed'),
            headers: common_response_headers }
        else
          { status: 200,
            body: success_envelope('tracking_id' => 'tx_ok', 'status' => 'sent',
                                   'device_id' => 1, 'template_id' => 1,
                                   'variant_index' => 0, 'phone' => '+8801617738431',
                                   'sent_at' => '2026-05-01T10:00:00Z',
                                   'wa_message_id' => 'wamid.x'),
            headers: common_response_headers }
        end
      end

      result = retry_client.messages.send(
        device_id: 1, template_id: 1, phone: '+8801617738431'
      )
      expect(result.tracking_id).to eq('tx_ok')
      expect(counter).to eq(3) # 1 try + 2 retries
      expect(seen_keys.uniq.size).to eq(1) # same key on every retry
    end

    it 'surfaces the last error when retries are exhausted' do
      stub_request(:post, send_url).to_return(
        status: 503,
        body: error_envelope('boom', error_code: 'send_failed'),
        headers: common_response_headers
      ).times(3).then.to_raise('should not be reached')

      expect do
        retry_client.messages.send(
          device_id: 1, template_id: 1, phone: '+8801617738431'
        )
      end.to raise_error(Walinko::ServerError)
    end

    it 'does not retry 4xx (other than 429)' do
      stub = stub_request(:post, send_url).to_return(
        status: 400,
        body: error_envelope('bad', error_code: 'bad_request'),
        headers: common_response_headers
      )

      expect do
        retry_client.messages.send(
          device_id: 1, template_id: 1, phone: '+8801617738431'
        )
      end.to raise_error(Walinko::BadRequestError)

      expect(stub).to have_been_requested.once
    end

    it 'retries on connection errors' do
      counter = 0
      stub_request(:post, send_url).to_return do |_req|
        counter += 1
        raise Errno::ECONNRESET if counter < 2

        { status: 200,
          body: success_envelope('tracking_id' => 'tx_ok', 'status' => 'sent',
                                 'device_id' => 1, 'template_id' => 1,
                                 'variant_index' => 0, 'phone' => '+8801617738431',
                                 'sent_at' => '2026-05-01T10:00:00Z',
                                 'wa_message_id' => 'wamid.x'),
          headers: common_response_headers }
      end

      result = retry_client.messages.send(
        device_id: 1, template_id: 1, phone: '+8801617738431'
      )
      expect(result.tracking_id).to eq('tx_ok')
    end

    it 'wraps exhausted connection errors as Walinko::ConnectionError' do
      stub_request(:post, send_url).to_raise(Errno::ECONNRESET)

      expect do
        retry_client.messages.send(
          device_id: 1, template_id: 1, phone: '+8801617738431'
        )
      end.to raise_error(Walinko::ConnectionError)
    end

    it 'retries 429 and respects Retry-After (capped)' do
      counter = 0
      sleep_arg = nil
      allow_any_instance_of(Walinko::HttpClient).to receive(:sleep) do |_, sec|
        sleep_arg = sec
      end

      stub_request(:post, send_url).to_return do |_req|
        counter += 1
        if counter < 2
          { status: 429,
            body: error_envelope('limited', error_code: 'rate_limited'),
            headers: common_response_headers('Retry-After' => '3') }
        else
          { status: 200,
            body: success_envelope('tracking_id' => 'tx_ok', 'status' => 'sent',
                                   'device_id' => 1, 'template_id' => 1,
                                   'variant_index' => 0, 'phone' => '+8801617738431',
                                   'sent_at' => '2026-05-01T10:00:00Z',
                                   'wa_message_id' => 'wamid.x'),
            headers: common_response_headers }
        end
      end

      retry_client.messages.send(
        device_id: 1, template_id: 1, phone: '+8801617738431'
      )

      expect(sleep_arg).to eq(3)
    end
  end
end
