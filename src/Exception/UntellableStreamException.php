<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Exception;

use RuntimeException;
class Untellable_Stream_Exception extends RuntimeException implements Exception_Interface
{
    public static function due_to_missing_resource(): self
    {
        return new self('No resource available; cannot tell position');
    }
    public static function due_to_php_error(): self
    {
        return new self('Error occurred during tell operation');
    }
    public static function for_callback_stream(): self
    {
        return new self('Callback streams cannot tell position');
    }
}