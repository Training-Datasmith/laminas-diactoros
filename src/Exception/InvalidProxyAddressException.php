<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Exception;

use function get_debug_type;
use function sprintf;
class Invalid_Proxy_Address_Exception extends RuntimeException implements Exception_Interface
{
    public static function for_invalid_proxy_argument(mixed $proxy): self
    {
        $type = get_debug_type($proxy);
        return new self(sprintf('Invalid proxy of type "%s" provided;' . ' must be a valid IPv4 or IPv6 address, optionally with a subnet mask provided' . ' or an array of such values', $type));
    }
    public static function for_address(string $address): self
    {
        return new self(sprintf('Invalid proxy address "%s" provided;' . ' must be a valid IPv4 or IPv6 address, optionally with a subnet mask provided', $address));
    }
}