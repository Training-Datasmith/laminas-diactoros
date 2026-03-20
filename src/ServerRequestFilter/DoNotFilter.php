<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Server_Request_Filter;

use Override;
use Psr\Http\Message\Server_Request_Interface;
final class Do_Not_Filter implements Filter_Server_Request_Interface
{
    #[Override]
    public function __invoke(Server_Request_Interface $request): Server_Request_Interface
    {
        return $request;
    }
}