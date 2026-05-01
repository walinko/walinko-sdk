# frozen_string_literal: true

require_relative 'lib/walinko/version'

Gem::Specification.new do |spec|
  spec.name        = 'walinko'
  spec.version     = Walinko::VERSION
  spec.authors     = ['Walinko']
  spec.email       = ['support@walinko.com']

  spec.summary     = 'Official Ruby SDK for the Walinko public API.'
  spec.description = <<~DESC
    Server-to-server Ruby client for the Walinko public API. Provides
    ergonomic helpers for sending transactional WhatsApp messages, idempotent
    retries, structured errors, and lookups by tracking id.
  DESC
  spec.homepage    = 'https://github.com/walinko/walinko-sdk'
  spec.license     = 'MIT'

  spec.required_ruby_version = '>= 3.1.0'

  spec.metadata = {
    'homepage_uri' => spec.homepage,
    'source_code_uri' => 'https://github.com/walinko/walinko-sdk/tree/main/sdks/ruby',
    'changelog_uri' => 'https://github.com/walinko/walinko-sdk/blob/main/sdks/ruby/CHANGELOG.md',
    'documentation_uri' => 'https://walinko.com/docs/api',
    'bug_tracker_uri' => 'https://github.com/walinko/walinko-sdk/issues',
    'rubygems_mfa_required' => 'true'
  }

  spec.files = Dir.chdir(__dir__) do
    Dir['lib/**/*.rb', 'README.md', 'CHANGELOG.md', 'LICENSE']
  end
  spec.require_paths = ['lib']

  spec.add_development_dependency 'rspec', '~> 3.13'
  spec.add_development_dependency 'rubocop', '~> 1.65'
  spec.add_development_dependency 'webmock', '~> 3.20'
end
