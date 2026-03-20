<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

/**
 * Retrieve the request method from the SAPI parameters.
 */
function marshal_method_from_sapi(array $server): string
{
    return $server['REQUEST_METHOD'] ?? 'GET';
}