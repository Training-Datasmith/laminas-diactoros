<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Exception;

use RuntimeException;
class Unrewindable_Stream_Exception extends RuntimeException implements Exception_Interface
{
    public static function for_callback_stream(): self
    {
        return new self('Callback streams cannot rewind position');
    }
}