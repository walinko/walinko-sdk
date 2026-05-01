# walinko (Ruby)

Official Ruby client for the [Walinko](https://walinko.com) public API.

> **Status:** scaffolding. The first usable release is `0.1.0` — the stub in
> `lib/walinko.rb` is wired up just enough that `require 'walinko'` works.

## Install

```bash
gem install walinko
```

…or in a `Gemfile`:

```ruby
gem 'walinko', '~> 0.1'
```

## Quick start (target API for `0.1.0`)

```ruby
require 'walinko'

client = Walinko::Client.new(
  api_key:     ENV.fetch('WALINKO_API_KEY'),
  base_url:    'https://api.walinko.com',  # optional
  timeout:     30,                          # optional, seconds
  max_retries: 2                            # optional
)

# Sync send — blocks until the message is delivered (or 504 timeout).
result = client.messages.send(
  device_id:     1,
  template_id:   12,
  variant_index: 0,                              # optional, nil = primary
  phone:         '+8801617738431',
  variables:     { name: 'Kazi', dist: 'Dhaka' }
)

puts result.tracking_id     # tx_...
puts result.wa_message_id   # 3EB0...
puts result.status          # "sent"

# Async enqueue + poll.
job = client.messages.enqueue(
  device_id: 1, template_id: 12,
  phone: '+8801617738431',
  variables: { name: 'Kazi', dist: 'Dhaka' }
)

final = client.messages.wait_until_done(job.tracking_id, timeout: 60)
puts final.status           # "sent" | "failed"
```

## Errors

Every error is a subclass of `Walinko::Error`. See
[`docs/error-codes.md`](../../docs/error-codes.md) for the full mapping.

```ruby
begin
  client.messages.send(...)
rescue Walinko::RateLimitError => e
  sleep e.retry_after
  retry
rescue Walinko::ValidationError => e
  warn e.fields  # { phone: ['must match pattern …'] }
rescue Walinko::DeviceDisconnectedError
  # tell the user to reconnect their device
rescue Walinko::Error => e
  Rails.logger.error("Walinko send failed: #{e.message}")
end
```

## Idempotency

The SDK auto-generates a UUID `Idempotency-Key` for every `send` / `enqueue`
call so retries are safe end-to-end. Pass `idempotency_key:` to set your own
(e.g. tying a send to your domain object).

## Rate limits

The server enforces 30 req/min/key. The SDK exposes the latest known
window state via:

```ruby
client.last_rate_limit
# => #<Walinko::RateLimitSnapshot limit=30 remaining=29 reset_at=...>
```

## Development

```bash
cd sdks/ruby
bundle install
bundle exec rspec
bundle exec rubocop
```

## License

[MIT](../../LICENSE)
