<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Exception;

use function get_debug_type;
use function is_string;
use Laminas\Diactoros\Server_Request_Filter\Filter_Using_X_Forwarded_Headers;
use function sprintf;
class Invalid_Forwarded_Header_Name_Exception extends RuntimeException implements Exception_Interface
{
    public static function for_header(mixed $name): self
    {
        if (!is_string($name)) {
            $name = sprintf('(value of type %s)', get_debug_type($name));
        }
        return new self(sprintf('Invalid X-Forwarded-* header name "%s" provided to %s', $name, Filter_Using_X_Forwarded_Headers::class));
    }
}