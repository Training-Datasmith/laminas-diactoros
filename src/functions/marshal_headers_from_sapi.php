<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use function array_filter;
use const ARRAY_FILTER_USE_KEY;
use function array_key_exists;
use function is_string;
use function str_starts_with;
use function strtolower;
use function strtr;
use function substr;
/**
 * @param array $server Values obtained from the SAPI (generally `$_SERVER`).
 * @return array<non-empty-string, mixed> Header/value pairs
 */
function marshal_headers_from_sapi(array $server): array
{
    $content_header_lookup = isset($server['LAMINAS_DIACTOROS_STRICT_CONTENT_HEADER_LOOKUP']) ? static function (string $key): bool {
        static $content_headers = ['CONTENT_TYPE' => true, 'CONTENT_LENGTH' => true, 'CONTENT_MD5' => true];
        return isset($content_headers[$key]);
    } : static fn(string $key): bool => str_starts_with($key, 'CONTENT_');
    $headers = [];
    foreach ($server as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        if ($key === '') {
            continue;
        }
        if ($value === '') {
            continue;
        }
        // Apache prefixes environment variables with REDIRECT_
        // if they are added by rewrite rules
        if (str_starts_with($key, 'REDIRECT_')) {
            $key = substr($key, 9);
            // We will not overwrite existing variables with the
            // prefixed versions, though
            if (array_key_exists($key, $server)) {
                continue;
            }
        }
        if (str_starts_with($key, 'HTTP_')) {
            $name = strtr(strtolower(substr($key, 5)), '_', '-');
            $headers[$name] = $value;
            continue;
        }
        if ($content_header_lookup($key)) {
            $name = strtr(strtolower($key), '_', '-');
            $headers[$name] = $value;
            continue;
        }
    }
    // Filter out integer keys.
    // These can occur if the translated header name is a string integer.
    // PHP will cast those to integers when assigned to an array.
    // This filters them out.
    return array_filter($headers, is_string(...), ARRAY_FILTER_USE_KEY);
}