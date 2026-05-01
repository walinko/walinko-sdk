<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * 422 — semantic validation failure.
 *
 * For DTO validation errors (the default class-validator path),
 * `fields()` returns a `[field => [reason, ...]]` map. For
 * `phone_not_on_whatsapp` the map is empty and the reason is in
 * `getMessage()`.
 */
class ValidationException extends ApiException
{
    /**
     * @return array<string, list<string>>
     */
    public function fields(): array
    {
        $fields = $this->details['fields'] ?? [];
        if (!\is_array($fields)) {
            return [];
        }

        $out = [];
        foreach ($fields as $name => $reasons) {
            if (!\is_string($name) || !\is_array($reasons)) {
                continue;
            }
            $out[$name] = array_values(array_map('strval', $reasons));
        }

        return $out;
    }
}
