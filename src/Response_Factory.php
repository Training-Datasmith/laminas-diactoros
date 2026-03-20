<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use Override;
use Psr\Http\Message\Response_Factory_Interface;
use Psr\Http\Message\Response_Interface;
class Response_Factory implements Response_Factory_Interface
{
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function create_response(int $code = 200, string $reason_phrase = ''): Response_Interface
    {
        return (new Response())->with_status($code, $reason_phrase);
    }
}