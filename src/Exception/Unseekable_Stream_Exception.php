<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Exception;

use RuntimeException;
class Unseekable_Stream_Exception extends RuntimeException implements Exception_Interface
{
    public static function due_to_configuration(): self
    {
        return new self('Stream is not seekable');
    }
    public static function due_to_missing_resource(): self
    {
        return new self('No resource available; cannot seek position');
    }
    public static function due_to_php_error(): self
    {
        return new self('Error seeking within stream');
    }
    public static function for_callback_stream(): self
    {
        return new self('Callback streams cannot seek position');
    }
}