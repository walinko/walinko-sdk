<?php

declare(strict_types=1);

namespace Walinko;

/**
 * Walinko PHP SDK client.
 *
 * This class is currently a stub. The full client (transport, messages
 * resource, errors, idempotency, retries) lands in Phase 1 of the SDK
 * rollout. The public surface is documented in `README.md` and pinned by
 * `docs/openapi.yaml` at the repo root.
 *
 * TODO(walinko-webhooks): reserve `$client->webhooks` for the future webhook
 * receiver helpers (signature verification, event dispatch). Adding them
 * later must remain non-breaking for v1.
 */
final class Client
{
    public const VERSION = '0.1.0-alpha1';

    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private int $maxRetries;

    /**
     * @param array{
     *     api_key: string,
     *     base_url?: string,
     *     timeout?: int,
     *     max_retries?: int,
     *     http_client?: \Psr\Http\Client\ClientInterface,
     *     logger?: \Psr\Log\LoggerInterface
     * } $options
     */
    public function __construct(array $options)
    {
        if (!isset($options['api_key']) || $options['api_key'] === '') {
            throw new \InvalidArgumentException('api_key is required');
        }

        $this->apiKey     = $options['api_key'];
        $this->baseUrl    = $options['base_url']    ?? 'https://api.walinko.com';
        $this->timeout    = (int) ($options['timeout']     ?? 30);
        $this->maxRetries = (int) ($options['max_retries'] ?? 2);
    }

    public function messages(): never
    {
        throw new \LogicException(
            'Walinko PHP SDK is in scaffolding. The Messages resource will land in 0.1.0.'
        );
    }
}
