<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Response;

use function get_debug_type;
use function is_string;
use Laminas\Diactoros\Exception;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\Stream_Interface;
use function sprintf;
/**
 * Plain text response.
 *
 * Allows creating a response by passing a string to the constructor;
 * by default, sets a status code of 200 and sets the Content-Type header to
 * text/plain.
 */
class Text_Response extends Response
{
    use Inject_Content_Type_Trait;
    /**
     * Create a plain text response.
     *
     * Produces a text response with a Content-Type of text/plain and a default
     * status of 200.
     *
     * @param string|StreamInterface $text String or stream for the message body.
     * @param int $status Integer status code for the response; 200 by default.
     * @param array<non-empty-string, string|string[]> $headers Array of headers to use at initialization.
     * @throws Exception\InvalidArgumentException If $text is neither a string or stream.
     */
    public function __construct($text, int $status = 200, array $headers = [])
    {
        parent::__construct($this->create_body($text), $status, $this->inject_content_type('text/plain; charset=utf-8', $headers));
    }
    /**
     * Create the message body.
     *
     * @param string|StreamInterface $text
     * @throws Exception\InvalidArgumentException If $text is neither a string or stream.
     */
    private function create_body($text): Stream_Interface
    {
        if ($text instanceof Stream_Interface) {
            return $text;
        }
        /** @psalm-suppress DocblockTypeContradiction */
        if (!is_string($text)) {
            throw new Exception\InvalidArgumentException(sprintf('Invalid content (%s) provided to %s', get_debug_type($text), self::class));
        }
        $body = new Stream('php://temp', 'wb+');
        $body->write($text);
        $body->rewind();
        return $body;
    }
}