<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Exception;

use RuntimeException;
class Unwritable_Stream_Exception extends RuntimeException implements Exception_Interface
{
    public static function due_to_configuration(): self
    {
        return new self('Stream is not writable');
    }
    public static function due_to_missing_resource(): self
    {
        return new self('No resource available; cannot write');
    }
    public static function due_to_php_error(): self
    {
        return new self('Error writing to stream');
    }
    public static function for_callback_stream(): self
    {
        return new self('Callback streams cannot write');
    }
}