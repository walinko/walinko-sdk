# Walinko SDKs

Official server-side SDKs for the [Walinko](https://walinko.com) public API — currently covering transactional WhatsApp message sending.

This monorepo houses every official SDK plus the shared OpenAPI spec, error-code contract, and examples.

## Packages

| Language | Package | Registry | Min runtime | Status |
|---|---|---|---|---|
| Ruby | `walinko` | [RubyGems](https://rubygems.org/gems/walinko) | Ruby 3.1+ | scaffolding |
| PHP | `walinko/sdk` | [Packagist](https://packagist.org/packages/walinko/sdk) | PHP 8.1+ | scaffolding |

More languages may follow (Python, Node, Go) — see [`docs/`](./docs) for the contract.

## Repo layout

```
walinko-sdk/
├── docs/
│   ├── openapi.yaml          # OpenAPI 3.1 spec — single source of truth
│   └── error-codes.md        # canonical error_code reference
├── examples/                 # runnable per-language examples
│   ├── ruby/
│   └── php/
├── sdks/
│   ├── ruby/                 # walinko gem
│   └── php/                  # walinko/sdk composer package
└── .github/workflows/        # CI: per-language tests, splitsh mirror, releases
```

PHP is mirrored to a read-only `walinko-php` repo via [`splitsh-lite`](https://github.com/splitsh/lite) on every push to `main` so Packagist can register it (Packagist requires `composer.json` at repo root). Ruby publishes from this repo directly.

## Quick start

### Ruby

```ruby
require 'walinko'

client = Walinko::Client.new(api_key: ENV['WALINKO_API_KEY'])

result = client.messages.send(
  device_id:     1,
  template_id:   12,
  variant_index: 0,                                 # optional
  phone:         '+8801617738431',
  variables:     { name: 'Kazi', dist: 'Dhaka' }
)

puts result.tracking_id   # tx_...
puts result.wa_message_id
```

### PHP

```php
use Walinko\Client;

$client = new Client(['api_key' => getenv('WALINKO_API_KEY')]);

$result = $client->messages->send([
    'device_id'     => 1,
    'template_id'   => 12,
    'variant_index' => 0,
    'phone'         => '+8801617738431',
    'variables'     => ['name' => 'Kazi', 'dist' => 'Dhaka'],
]);

echo $result->tracking_id;
echo $result->wa_message_id;
```

## Versioning

- All SDKs follow [SemVer](https://semver.org). Major versions track the public API contract version (`/api/v1/...`).
- Each SDK keeps its own `CHANGELOG.md` under `sdks/<lang>/CHANGELOG.md`.

## Contributing

See per-package READMEs for setup. The OpenAPI spec under `docs/openapi.yaml` is the contract — change requests should land there first.

## License

[MIT](./LICENSE)
