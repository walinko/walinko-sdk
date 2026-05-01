# frozen_string_literal: true

# Run with: WALINKO_API_KEY=walk_live_... ruby examples/ruby/send_sync.rb
#
# This example demonstrates the *target* API for Walinko::Client#messages#send.
# It will start working when the Ruby SDK reaches 0.1.0.

require 'walinko'

client = Walinko::Client.new(api_key: ENV.fetch('WALINKO_API_KEY'))

result = client.messages.send(
  device_id:     1,
  template_id:   12,
  variant_index: 0,
  phone:         '+8801617738431',
  variables:     { name: 'Kazi', dist: 'Dhaka' }
)

puts "tracking_id:   #{result.tracking_id}"
puts "wa_message_id: #{result.wa_message_id}"
puts "status:        #{result.status}"
