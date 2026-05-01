# frozen_string_literal: true

# Run with: WALINKO_API_KEY=walk_live_... ruby examples/ruby/send_sync.rb
#
# Synchronous send: blocks until the WhatsApp gateway acknowledges
# delivery (or the server's 15s timeout fires).

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
