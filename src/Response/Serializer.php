<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Response;

use Laminas\Diactoros\Abstract_Serializer;
use Laminas\Diactoros\Exception;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;
use function preg_match;
use Psr\Http\Message\Response_Interface;
use Psr\Http\Message\Stream_Interface;
use function sprintf;
final class Serializer extends Abstract_Serializer
{
    /**
     * Deserialize a response string to a response instance.
     *
     * @throws Exception\SerializationException When errors occur parsing the message.
     */
    public static function from_string(string $message): Response
    {
        $stream = new Stream('php://temp', 'wb+');
        $stream->write($message);
        return static::from_stream($stream);
    }
    /**
     * Parse a response from a stream.
     *
     * @throws Exception\InvalidArgumentException When the stream is not readable.
     * @throws Exception\SerializationException When errors occur parsing the message.
     */
    public static function from_stream(Stream_Interface $stream): Response
    {
        if (!$stream->is_readable() || !$stream->is_seekable()) {
            throw new Exception\InvalidArgumentException('Message stream must be both readable and seekable');
        }
        $stream->rewind();
        [$version, $status, $reason_phrase] = self::get_status_line($stream);
        [$headers, $body] = self::split_stream($stream);
        return (new Response($body, $status, $headers))->with_protocol_version($version)->with_status((int) $status, $reason_phrase);
    }
    /**
     * Create a string representation of a response.
     */
    public static function to_string(Response_Interface $response): string
    {
        $reason_phrase = $response->get_reason_phrase();
        $headers = self::serialize_headers($response->get_headers());
        $body = (string) $response->get_body();
        $format = 'HTTP/%s %d%s%s%s';
        if (!empty($headers)) {
            $headers = "\r\n" . $headers;
        }
        $headers .= "\r\n\r\n";
        return sprintf($format, $response->get_protocol_version(), $response->get_status_code(), $reason_phrase ? ' ' . $reason_phrase : '', $headers, $body);
    }
    /**
     * Retrieve the status line for the message.
     *
     * @return array Array with three elements: 0 => version, 1 => status, 2 => reason
     * @throws Exception\SerializationException If line is malformed.
     */
    private static function get_status_line(Stream_Interface $stream): array
    {
        $line = self::get_line($stream);
        if (!preg_match('#^HTTP/(?P<version>[1-9]\d*\.\d) (?P<status>[1-5]\d{2})(\s+(?P<reason>.+))?$#', $line, $matches)) {
            throw Exception\Serialization_Exception::for_invalid_status_line();
        }
        return [$matches['version'], (int) $matches['status'], $matches['reason'] ?? ''];
    }
}