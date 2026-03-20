<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use function array_pop;
use function assert;
use function implode;
use function is_string;
use function preg_match;
use Psr\Http\Message\Stream_Interface;
use function sprintf;
use function str_replace;
use function trim;
use function ucwords;
/**
 * Provides base functionality for request and response de/serialization
 * strategies, including functionality for retrieving a line at a time from
 * the message, splitting headers from the body, and serializing headers.
 */
abstract class Abstract_Serializer
{
    public const CR = "\r";
    public const EOL = "\r\n";
    public const LF = "\n";
    /**
     * Retrieve a single line from the stream.
     *
     * Retrieves a line from the stream; a line is defined as a sequence of
     * characters ending in a CRLF sequence.
     *
     * @throws Exception\DeserializationException If the sequence contains a CR
     *     or LF in isolation, or ends in a CR.
     */
    protected static function get_line(Stream_Interface $stream): string
    {
        $line = '';
        $cr_found = false;
        while (!$stream->eof()) {
            $char = $stream->read(1);
            if ($cr_found && $char === self::LF) {
                $cr_found = false;
                break;
            }
            // CR NOT followed by LF
            if ($cr_found && $char !== self::LF) {
                throw Exception\Deserialization_Exception::for_unexpected_carriage_return();
            }
            // LF in isolation
            if (!$cr_found && $char === self::LF) {
                throw Exception\Deserialization_Exception::for_unexpected_line_feed();
            }
            // CR found; do not append
            if ($char === self::CR) {
                $cr_found = true;
                continue;
            }
            // Any other character: append
            $line .= $char;
        }
        // CR found at end of stream
        if ($cr_found) {
            throw Exception\Deserialization_Exception::for_unexpected_end_of_headers();
        }
        return $line;
    }
    /**
     * Split the stream into headers and body content.
     *
     * Returns an array containing two elements
     *
     * - The first is an array of headers
     * - The second is a StreamInterface containing the body content
     *
     * @throws Exception\DeserializationException For invalid headers.
     */
    protected static function split_stream(Stream_Interface $stream): array
    {
        $headers = [];
        $current_header = false;
        while ($line = self::get_line($stream)) {
            if (preg_match(';^(?P<name>[!#$%&\'*+.^_`\|~0-9a-zA-Z-]+):(?P<value>.*)$;', $line, $matches)) {
                $current_header = $matches['name'];
                if (!isset($headers[$current_header])) {
                    $headers[$current_header] = [];
                }
                $headers[$current_header][] = trim($matches['value'], "\t ");
                continue;
            }
            if ($current_header === false) {
                throw Exception\Deserialization_Exception::for_invalid_header();
            }
            if (!preg_match('#^[ \t]#', $line)) {
                throw Exception\Deserialization_Exception::for_invalid_header_continuation();
            }
            // Append continuation to last header value found
            $value = array_pop($headers[$current_header]);
            assert(is_string($value));
            $headers[$current_header][] = $value . ' ' . trim($line, "\t ");
        }
        // use RelativeStream to avoid copying initial stream into memory
        return [$headers, new Relative_Stream($stream, $stream->tell())];
    }
    /**
     * Serialize headers to string values.
     *
     * @psalm-param array<non-empty-string, string[]> $headers
     */
    protected static function serialize_headers(array $headers): string
    {
        $lines = [];
        foreach ($headers as $header => $values) {
            $normalized = self::filter_header($header);
            foreach ($values as $value) {
                $lines[] = sprintf('%s: %s', $normalized, $value);
            }
        }
        return implode("\r\n", $lines);
    }
    /**
     * Filter a header name to wordcase
     *
     * @param string $header
     */
    protected static function filter_header($header): string
    {
        $filtered = str_replace('-', ' ', $header);
        $filtered = ucwords($filtered);
        return str_replace(' ', '-', $filtered);
    }
}