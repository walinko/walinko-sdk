# frozen_string_literal: true

# Run with: WALINKO_API_KEY=walk_live_... ruby examples/ruby/send_async_and_poll.rb

require 'walinko'

client = Walinko::Client.new(api_key: ENV.fetch('WALINKO_API_KEY'))

job = client.messages.enqueue(
  device_id:   1,
  template_id: 12,
  phone:       '+8801617738431',
  variables:   { name: 'Kazi', dist: 'Dhaka' }
)

puts "queued: #{job.tracking_id} (poll #{job.status_url})"

final = client.messages.wait_until_done(job.tracking_id, timeout: 60, interval: 2)

if final.status == 'sent'
  puts "delivered (wa_message_id=#{final.wa_message_id})"
else
  warn "failed: #{final.error_code} - #{final.error_message}"
end
