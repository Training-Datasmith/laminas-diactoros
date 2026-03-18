<?php

declare(strict_types=1);

namespace Laminas\Diactoros\Exception;

use function sprintf;

use UnexpectedValueException;

class UnrecognizedProtocolVersionException extends UnexpectedValueException implements ExceptionInterface
{
    public static function forVersion(string $version): self
    {
        return new self(sprintf('Unrecognized protocol version (%s)', $version));
    }
}
