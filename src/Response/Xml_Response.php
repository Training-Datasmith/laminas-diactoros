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
 * XML response.
 *
 * Allows creating a response by passing an XML string to the constructor; by default,
 * sets a status code of 200 and sets the Content-Type header to application/xml.
 */
class Xml_Response extends Response
{
    use Inject_Content_Type_Trait;
    /**
     * Create an XML response.
     *
     * Produces an XML response with a Content-Type of application/xml and a default
     * status of 200.
     *
     * @param string|StreamInterface $xml String or stream for the message body.
     * @param int $status Integer status code for the response; 200 by default.
     * @param array<non-empty-string, string|string[]> $headers Array of headers to use at initialization.
     * @throws Exception\InvalidArgumentException If $text is neither a string or stream.
     */
    public function __construct($xml, int $status = 200, array $headers = [])
    {
        parent::__construct($this->create_body($xml), $status, $this->inject_content_type('application/xml; charset=utf-8', $headers));
    }
    /**
     * Create the message body.
     *
     * @param string|StreamInterface $xml
     * @throws Exception\InvalidArgumentException If $xml is neither a string or stream.
     */
    private function create_body($xml): Stream_Interface
    {
        if ($xml instanceof Stream_Interface) {
            return $xml;
        }
        /** @psalm-suppress DocblockTypeContradiction */
        if (!is_string($xml)) {
            throw new Exception\InvalidArgumentException(sprintf('Invalid content (%s) provided to %s', get_debug_type($xml), self::class));
        }
        $body = new Stream('php://temp', 'wb+');
        $body->write($xml);
        $body->rewind();
        return $body;
    }
}