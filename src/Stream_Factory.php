<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use function assert;
use function fopen;
use function fwrite;
use function is_resource;
use Override;
use Psr\Http\Message\Stream_Factory_Interface;
use Psr\Http\Message\Stream_Interface;
use function rewind;
class Stream_Factory implements Stream_Factory_Interface
{
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function create_stream(string $content = ''): Stream_Interface
    {
        $resource = fopen('php://temp', 'r+');
        assert(is_resource($resource), 'Something is really wrong if PHP failed to open stream in memory');
        fwrite($resource, $content);
        rewind($resource);
        return $this->create_stream_from_resource($resource);
    }
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function create_stream_from_file(string $filename, string $mode = 'r'): Stream_Interface
    {
        return new Stream($filename, $mode);
    }
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function create_stream_from_resource($resource): Stream_Interface
    {
        return new Stream($resource);
    }
}