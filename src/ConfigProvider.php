<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use Psr\Http\Message\Request_Factory_Interface;
use Psr\Http\Message\Response_Factory_Interface;
use Psr\Http\Message\Server_Request_Factory_Interface;
use Psr\Http\Message\Stream_Factory_Interface;
use Psr\Http\Message\Uploaded_File_Factory_Interface;
use Psr\Http\Message\Uri_Factory_Interface;
class Config_Provider
{
    public const CONFIG_KEY = 'laminas-diactoros';
    public const X_FORWARDED = 'x-forwarded-request-filter';
    public const X_FORWARDED_TRUSTED_PROXIES = 'trusted-proxies';
    public const X_FORWARDED_TRUSTED_HEADERS = 'trusted-headers';
    /**
     * Retrieve configuration for laminas-diactoros.
     */
    public function __invoke(): array
    {
        return ['dependencies' => $this->get_dependencies(), self::CONFIG_KEY => $this->get_component_config()];
    }
    /**
     * Returns the container dependencies.
     * Maps factory interfaces to factories.
     */
    public function get_dependencies(): array
    {
        // @codingStandardsIgnoreStart
        return ['invokables' => [Request_Factory_Interface::class => Request_Factory::class, Response_Factory_Interface::class => Response_Factory::class, Stream_Factory_Interface::class => Stream_Factory::class, Server_Request_Factory_Interface::class => Server_Request_Factory::class, Uploaded_File_Factory_Interface::class => Uploaded_File_Factory::class, Uri_Factory_Interface::class => Uri_Factory::class]];
        // @codingStandardsIgnoreEnd
    }
    public function get_component_config(): array
    {
        return [self::X_FORWARDED => [self::X_FORWARDED_TRUSTED_PROXIES => '', self::X_FORWARDED_TRUSTED_HEADERS => []]];
    }
}