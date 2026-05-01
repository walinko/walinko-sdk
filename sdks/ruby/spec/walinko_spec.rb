# frozen_string_literal: true

require 'spec_helper'

RSpec.describe Walinko do
  it 'pins the SDK version' do
    expect(Walinko::VERSION).to eq('0.1.0')
  end

  describe Walinko::Client do
    it 'rejects a nil api_key' do
      expect { described_class.new(api_key: nil) }.to raise_error(ArgumentError, /api_key/)
    end

    it 'rejects an empty api_key' do
      expect { described_class.new(api_key: '') }.to raise_error(ArgumentError, /api_key/)
    end

    it 'rejects a nonsense max_retries' do
      expect do
        described_class.new(api_key: 'walk_live_x.y', max_retries: -1)
      end.to raise_error(ArgumentError, /max_retries/)
    end

    it 'exposes a Messages resource' do
      client = described_class.new(api_key: 'walk_live_x.y', base_url: 'https://api.example.com')
      expect(client.messages).to be_a(Walinko::Messages)
    end

    it 'returns nil rate-limit / request-id before any call' do
      client = described_class.new(api_key: 'walk_live_x.y')
      expect(client.last_rate_limit).to be_nil
      expect(client.last_request_id).to be_nil
    end

    it 'strips trailing slashes from base_url' do
      client = described_class.new(api_key: 'walk_live_x.y', base_url: 'https://api.example.com/')
      expect(client.config.base_url).to eq('https://api.example.com')
    end
  end
end
