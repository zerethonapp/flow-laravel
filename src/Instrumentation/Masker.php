<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Instrumentation;

/**
 * PHP port of flow-core's src/utils/mask.ts — same two-part strategy: mask
 * values under sensitive-looking keys entirely, and mask email-shaped
 * string values wherever they appear. flow-core's own masking only ever
 * runs downstream, in flow-engine's processing step — never at capture
 * time, in either language (see Tickets/"Zerethon Flow - Intelligence
 * Engine Enhancement.md"'s cross-package finding). This applies the same
 * logic here, at capture time, in this package — closing the gap flagged
 * in Tickets/trust-platform-documentation.md's compliance review: request/
 * external-call URLs were captured completely unmasked.
 *
 * Not exhaustive — a heuristic covering the common cases (query-string
 * tokens, named route parameters like a password-reset token, meta keys
 * like `password`/`token`/`apiKey`), same "not a full spec implementation,
 * good enough for a v1" scope as this package's robots.txt-equivalent
 * choices elsewhere. Path segments that aren't a named route parameter
 * (e.g. a token embedded in a static, non-route-parameter path) are not
 * masked — there's no reliable way to distinguish that from a legitimate
 * resource identifier without a route parameter name to key off of.
 */
final class Masker
{
    private const SENSITIVE_KEY_PATTERN = '/token|secret|password|api[-_]?key|credential|authorization|session/i';

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function maskArray(array $input): array
    {
        $result = [];

        foreach ($input as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1) {
                $result[$key] = '****';
                continue;
            }

            if (is_string($key) && strtolower($key) === 'url' && is_string($value)) {
                $result[$key] = self::maskUrl($value);
                continue;
            }

            if (is_array($value)) {
                $result[$key] = self::maskArray($value);
            } elseif (is_string($value)) {
                $result[$key] = self::maskString($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** Masks an email-shaped string (`j***@example.com`); anything else passes through unchanged. */
    public static function maskString(string $value): string
    {
        if (str_contains($value, '@') && preg_match('/^([^@])[^@]*(@.+)$/', $value, $matches) === 1) {
            return $matches[1] . '***' . $matches[2];
        }

        return $value;
    }

    /**
     * Masks sensitive-named query-parameter *values*, leaving the path,
     * host, and parameter names visible — so the trace still shows which
     * endpoint was called and what params it accepted, just not the
     * sensitive values. Note: round-tripping through parse_str()/
     * http_build_query() can normalize encoding/param order slightly —
     * acceptable for a debugging trace, not meant to reproduce the exact
     * original bytes.
     */
    public static function maskUrl(string $url): string
    {
        $queryStart = strpos($url, '?');
        if ($queryStart === false) {
            return $url;
        }

        $base = substr($url, 0, $queryStart);
        parse_str(substr($url, $queryStart + 1), $params);

        if ($params === []) {
            return $url;
        }

        $masked = [];
        foreach ($params as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1) {
                $masked[$key] = '****';
            } elseif (is_string($value)) {
                $masked[$key] = self::maskString($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $base . '?' . http_build_query($masked);
    }

    /**
     * Masks a request URI in two passes: first, any sensitive-named route
     * parameter's literal value (e.g. the {token} in a password-reset
     * route `/reset-password/{token}`, which Laravel puts in the URI path,
     * not the query string) is replaced wherever it appears; then the
     * query string is masked via maskUrl().
     *
     * @param array<string, mixed> $routeParameters
     */
    public static function maskRequestUri(string $uri, array $routeParameters): string
    {
        foreach ($routeParameters as $name => $value) {
            if (is_string($name) && is_string($value) && $value !== '' && preg_match(self::SENSITIVE_KEY_PATTERN, $name) === 1) {
                $uri = str_replace($value, '****', $uri);
            }
        }

        return self::maskUrl($uri);
    }
}
