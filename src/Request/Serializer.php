<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Request;

use Laminas\Diactoros\Abstract_Serializer;
use Laminas\Diactoros\Exception;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\Uri;
use function preg_match;
use Psr\Http\Message\Request_Interface;
use Psr\Http\Message\Stream_Interface;
use function sprintf;
/**
 * Serialize (cast to string) or deserialize (cast string to Request) messages.
 *
 * This class provides functionality for serializing a RequestInterface instance
 * to a string, as well as the reverse operation of creating a Request instance
 * from a string/stream representing a message.
 */
final class Serializer extends Abstract_Serializer
{
    /**
     * Deserialize a request string to a request instance.
     *
     * Internally, casts the message to a stream and invokes fromStream().
     *
     * @throws Exception\SerializationException When errors occur parsing the message.
     */
    public static function from_string(string $message): Request
    {
        $stream = new Stream('php://temp', 'wb+');
        $stream->write($message);
        return self::from_stream($stream);
    }
    /**
     * Deserialize a request stream to a request instance.
     *
     * @throws Exception\InvalidArgumentException If the message stream is not readable or seekable.
     * @throws Exception\SerializationException If an invalid request line is detected.
     */
    public static function from_stream(Stream_Interface $stream): Request
    {
        if (!$stream->is_readable() || !$stream->is_seekable()) {
            throw new Exception\InvalidArgumentException('Message stream must be both readable and seekable');
        }
        $stream->rewind();
        [$method, $request_target, $version] = self::get_request_line($stream);
        $uri = self::create_uri_from_request_target($request_target);
        [$headers, $body] = self::split_stream($stream);
        return (new Request($uri, $method, $body, $headers))->with_protocol_version($version)->with_request_target($request_target);
    }
    /**
     * Serialize a request message to a string.
     */
    public static function to_string(Request_Interface $request): string
    {
        $http_method = $request->get_method();
        $headers = self::serialize_headers($request->get_headers());
        $body = (string) $request->get_body();
        $format = '%s %s HTTP/%s%s%s';
        if (!empty($headers)) {
            $headers = "\r\n" . $headers;
        }
        if (!empty($body)) {
            $headers .= "\r\n\r\n";
        }
        return sprintf($format, $http_method, $request->get_request_target(), $request->get_protocol_version(), $headers, $body);
    }
    /**
     * Retrieve the components of the request line.
     *
     * Retrieves the first line of the stream and parses it, raising an
     * exception if it does not follow specifications; if valid, returns a list
     * with the method, target, and version, in that order.
     *
     * @throws Exception\SerializationException
     */
    private static function get_request_line(Stream_Interface $stream): array
    {
        $request_line = self::get_line($stream);
        if (!preg_match('#^(?P<method>[!\#$%&\'*+.^_`|~a-zA-Z0-9-]+) (?P<target>[^\s]+) HTTP/(?P<version>[1-9]\d*\.\d+)$#', $request_line, $matches)) {
            throw Exception\Serialization_Exception::for_invalid_request_line();
        }
        return [$matches['method'], $matches['target'], $matches['version']];
    }
    /**
     * Create and return a Uri instance based on the provided request target.
     *
     * If the request target is of authority or asterisk form, an empty Uri
     * instance is returned; otherwise, the value is used to create and return
     * a new Uri instance.
     */
    private static function create_uri_from_request_target(string $request_target): Uri
    {
        if (preg_match('#^https?://#', $request_target)) {
            return new Uri($request_target);
        }
        if (preg_match('#^(\*|[^/])#', $request_target)) {
            return new Uri();
        }
        return new Uri($request_target);
    }
}