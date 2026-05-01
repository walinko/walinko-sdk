# walinko (Ruby)

Official Ruby client for the [Walinko](https://walinko.com) public API.

Send transactional WhatsApp messages from your Ruby app with idempotent
retries, structured errors, and a tiny dependency footprint (Ruby stdlib
only).

* Ruby 3.1+
* Zero runtime dependencies (only `net/http` + `json` from stdlib)
* MIT licensed

## Install

```bash
gem install walinko
```

…or in a `Gemfile`:

```ruby
gem 'walinko', '~> 0.1'
```

## Quick start

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

## Looking up a delivery

```ruby
status = client.messages.fetch('tx_767fd2faca0f4037b2a2bbcb91e5735f')
status.sent?         # true / false
status.error_code    # nil if sent, e.g. "phone_not_on_whatsapp" if failed
status.wa_message_id # WhatsApp's id, set on success
status.created_at    # Time
status.sent_at       # Time, nil while pending
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

## Retries

The SDK auto-retries idempotently on:

| Trigger                 | Behaviour                                       |
| ----------------------- | ----------------------------------------------- |
| Network errors          | Exponential backoff with jitter                 |
| HTTP 429                | Honours `Retry-After` (capped at 60s)           |
| HTTP 500/502/503/504    | Exponential backoff with jitter                 |

`max_retries` (default 2) controls how many additional attempts are made.
4xx responses (other than 429) are surfaced immediately — the request is
malformed or the server has rejected it on application grounds, and no
amount of retrying will help.

## Rate limits

The server enforces 30 req/min/key (sliding window). The SDK exposes the
latest known window state via:

```ruby
client.last_rate_limit
# => #<Walinko::RateLimitSnapshot limit=30 remaining=29>
client.last_request_id
# => "req_4f2c..."  (handy for support tickets)
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
