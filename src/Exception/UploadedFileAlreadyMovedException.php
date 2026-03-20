<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Exception;

use RuntimeException;
use Throwable;
class Uploaded_File_Already_Moved_Exception extends RuntimeException implements Exception_Interface
{
    /** {@inheritDoc} */
    public function __construct(string $message = 'Cannot retrieve stream after it has already moved', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}