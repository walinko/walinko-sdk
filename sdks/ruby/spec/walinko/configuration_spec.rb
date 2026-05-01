# frozen_string_literal: true

require 'spec_helper'

RSpec.describe Walinko::Configuration do
  it 'applies sensible defaults' do
    cfg = described_class.new(api_key: 'walk_live_x.y')
    expect(cfg.base_url).to eq('https://api.walinko.com')
    expect(cfg.timeout).to eq(30)
    expect(cfg.open_timeout).to eq(10)
    expect(cfg.max_retries).to eq(2)
    expect(cfg.logger).to be_nil
  end

  it 'rejects an empty api_key' do
    expect { described_class.new(api_key: '') }.to raise_error(ArgumentError, /api_key/)
  end

  it 'rejects a nil base_url' do
    expect do
      described_class.new(api_key: 'walk_live_x.y', base_url: nil)
    end.to raise_error(ArgumentError, /base_url/)
  end

  it 'rejects timeout <= 0' do
    expect do
      described_class.new(api_key: 'walk_live_x.y', timeout: 0)
    end.to raise_error(ArgumentError, /timeout/)
  end

  it 'rejects open_timeout <= 0' do
    expect do
      described_class.new(api_key: 'walk_live_x.y', open_timeout: 0)
    end.to raise_error(ArgumentError, /open_timeout/)
  end

  it 'rejects negative max_retries' do
    expect do
      described_class.new(api_key: 'walk_live_x.y', max_retries: -1)
    end.to raise_error(ArgumentError, /max_retries/)
  end
end
