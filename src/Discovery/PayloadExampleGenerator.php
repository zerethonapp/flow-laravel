<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Discovery;

/**
 * Generates an example request payload from ValidationNormalizer's
 * normalized field descriptors. This is a template only — a developer
 * reviewing the route in the Dashboard, never something Flow submits on
 * its own. See the Application Discovery Contract's `payload` field.
 */
final class PayloadExampleGenerator
{
    /**
     * @param array<int, array{name: string, type: string, required: bool, nullable: bool, format: string|null}> $fields
     * @return array<string, mixed>
     */
    public function generate(array $fields): array
    {
        $payload = [];

        foreach ($fields as $field) {
            $payload[$field['name']] = $this->exampleFor($field);
        }

        return $payload;
    }

    /**
     * @param array{name: string, type: string, required: bool, nullable: bool, format: string|null} $field
     */
    private function exampleFor(array $field): mixed
    {
        if ($field['nullable'] && !$field['required']) {
            return null;
        }

        if ($field['format'] !== null) {
            return match ($field['format']) {
                'email' => 'user@example.com',
                'uuid' => '00000000-0000-0000-0000-000000000000',
                'url' => 'https://example.com',
                default => '',
            };
        }

        return match ($field['type']) {
            // Not an empty string: Laravel's own default
            // ConvertEmptyStringsToNull middleware (part of the global
            // stack every API request passes through) silently rewrites
            // "" to null before it ever reaches this payload's eventual
            // storage — an empty-string placeholder would never survive
            // a real round trip through Flow API. "string" is also a more
            // informative placeholder anyway (matches common OpenAPI
            // example-generation convention).
            'string' => 'string',
            'integer', 'number' => 0,
            'boolean' => false,
            'array' => [],
            'date' => now()->toDateString(),
            'file' => null,
            default => null,
        };
    }
}
