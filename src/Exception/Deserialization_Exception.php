<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Exception;

use Throwable;
use UnexpectedValueException;
class Deserialization_Exception extends UnexpectedValueException implements Exception_Interface
{
    public static function for_invalid_header(): self
    {
        throw new self('Invalid header detected');
    }
    public static function for_invalid_header_continuation(): self
    {
        throw new self('Invalid header continuation');
    }
    public static function for_request_from_array(Throwable $previous): self
    {
        return new self('Cannot deserialize request', (int) $previous->get_code(), $previous);
    }
    public static function for_response_from_array(Throwable $previous): self
    {
        return new self('Cannot deserialize response', (int) $previous->get_code(), $previous);
    }
    public static function for_unexpected_carriage_return(): self
    {
        throw new self('Unexpected carriage return detected');
    }
    public static function for_unexpected_end_of_headers(): self
    {
        throw new self('Unexpected end of headers');
    }
    public static function for_unexpected_line_feed(): self
    {
        throw new self('Unexpected line feed detected');
    }
}