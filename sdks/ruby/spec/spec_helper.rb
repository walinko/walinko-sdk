# frozen_string_literal: true

$LOAD_PATH.unshift File.expand_path('../lib', __dir__)

require 'walinko'
require 'webmock/rspec'

RSpec.configure do |config|
  config.before { WebMock.disable_net_connect!(allow_localhost: false) }
  config.expect_with :rspec do |c|
    c.syntax = :expect
  end
  config.disable_monkey_patching!
  config.order = :random
  Kernel.srand config.seed
end
