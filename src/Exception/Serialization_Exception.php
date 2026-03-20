<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Exception;

use UnexpectedValueException;
class Serialization_Exception extends UnexpectedValueException implements Exception_Interface
{
    public static function for_invalid_request_line(): self
    {
        return new self('Invalid request line detected');
    }
    public static function for_invalid_status_line(): self
    {
        return new self('No status line detected');
    }
}