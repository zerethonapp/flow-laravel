<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Discovery;

/**
 * Turns a Laravel FormRequest::rules() array (framework-specific syntax —
 * pipe-strings, arrays of strings, or non-string Rule objects) into the
 * Application Discovery Contract's normalized, framework-independent
 * `validation.fields[]` shape. The raw input stays available separately
 * under `framework.laravel.rules` — this class never discards information,
 * it just also produces a normalized view alongside it.
 */
final class ValidationNormalizer
{
    /**
     * @param array<string, mixed> $rawRules
     * @return array<int, array{name: string, type: string, required: bool, nullable: bool, format: string|null}>
     */
    public function normalize(array $rawRules): array
    {
        $fields = [];

        foreach ($rawRules as $field => $spec) {
            if (!is_string($field) || $field === '') {
                continue;
            }

            $tokens = $this->tokensFor($spec);
            $ruleNames = array_map(fn (string $token): string => explode(':', $token, 2)[0], $tokens);

            $fields[] = [
                'name' => $field,
                'type' => $this->inferType($ruleNames),
                'required' => in_array('required', $ruleNames, true),
                'nullable' => in_array('nullable', $ruleNames, true),
                'format' => $this->inferFormat($ruleNames),
            ];
        }

        return $fields;
    }

    /**
     * @return string[]
     */
    private function tokensFor(mixed $spec): array
    {
        if (is_string($spec)) {
            return array_values(array_filter(explode('|', $spec), static fn (string $t): bool => $t !== ''));
        }

        if (!is_array($spec)) {
            return [];
        }

        $tokens = [];
        foreach ($spec as $item) {
            // Non-string entries (Rule::in(...), closures, Rule objects) are
            // real Laravel constructs this normalizer can't safely
            // stringify without executing them — skipped, not guessed at.
            if (!is_string($item)) {
                continue;
            }

            foreach (explode('|', $item) as $token) {
                if ($token !== '') {
                    $tokens[] = $token;
                }
            }
        }

        return $tokens;
    }

    /**
     * @param string[] $ruleNames
     */
    private function inferType(array $ruleNames): string
    {
        return match (true) {
            in_array('array', $ruleNames, true) => 'array',
            in_array('boolean', $ruleNames, true), in_array('bool', $ruleNames, true) => 'boolean',
            in_array('integer', $ruleNames, true), in_array('int', $ruleNames, true) => 'integer',
            in_array('numeric', $ruleNames, true) => 'number',
            in_array('file', $ruleNames, true), in_array('image', $ruleNames, true) => 'file',
            in_array('date', $ruleNames, true), in_array('date_format', $ruleNames, true) => 'date',
            in_array('string', $ruleNames, true),
            in_array('email', $ruleNames, true),
            in_array('uuid', $ruleNames, true),
            in_array('url', $ruleNames, true) => 'string',
            default => 'unknown',
        };
    }

    /**
     * @param string[] $ruleNames
     */
    private function inferFormat(array $ruleNames): ?string
    {
        return match (true) {
            in_array('email', $ruleNames, true) => 'email',
            in_array('uuid', $ruleNames, true) => 'uuid',
            in_array('url', $ruleNames, true) => 'url',
            default => null,
        };
    }
}
