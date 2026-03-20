<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Exception;

use function sprintf;
use UnexpectedValueException;
class Unrecognized_Protocol_Version_Exception extends UnexpectedValueException implements Exception_Interface
{
    public static function for_version(string $version): self
    {
        return new self(sprintf('Unrecognized protocol version (%s)', $version));
    }
}