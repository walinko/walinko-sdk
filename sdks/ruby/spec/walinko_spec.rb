# frozen_string_literal: true

require 'walinko'

RSpec.describe Walinko do
  it 'has a version' do
    expect(Walinko::VERSION).to be_a(String)
  end

  describe Walinko::Client do
    it 'requires an api_key' do
      expect { described_class.new(api_key: nil) }.to raise_error(ArgumentError)
    end

    it 'rejects an empty api_key' do
      expect { described_class.new(api_key: '') }.to raise_error(ArgumentError)
    end

    it 'accepts a base_url override' do
      client = described_class.new(api_key: 'walk_test_x.y', base_url: 'https://api.example.com')
      expect(client).to be_a(described_class)
    end

    it 'raises NotImplementedError until 0.1.0 lands' do
      client = described_class.new(api_key: 'walk_test_x.y')
      expect { client.messages }.to raise_error(NotImplementedError)
    end
  end
end
