<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use Override;
use Psr\Http\Message\Request_Factory_Interface;
use Psr\Http\Message\Request_Interface;
class Request_Factory implements Request_Factory_Interface
{
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function create_request(string $method, $uri): Request_Interface
    {
        return new Request($uri, $method);
    }
}