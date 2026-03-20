<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Response;

use function array_keys;
use function array_reduce;
use function strtolower;
trait Inject_Content_Type_Trait
{
    /**
     * Inject the provided Content-Type, if none is already present.
     *
     * @param array<non-empty-string, string|string[]> $headers
     * @return array<non-empty-string, string|string[]> Headers with injected Content-Type
     */
    private function inject_content_type(string $content_type, array $headers): array
    {
        $has_content_type = array_reduce(array_keys($headers), static fn(bool $carry, string $item): bool => $carry ?: strtolower($item) === 'content-type', false);
        if (!$has_content_type) {
            $headers['content-type'] = [$content_type];
        }
        return $headers;
    }
}