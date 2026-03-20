<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Exception;

use RuntimeException;
use Throwable;
class Invalid_Stream_Pointer_Position_Exception extends RuntimeException implements Exception_Interface
{
    /** {@inheritDoc} */
    public function __construct(string $message = 'Invalid pointer position', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}