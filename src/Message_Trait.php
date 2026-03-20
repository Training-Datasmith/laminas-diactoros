<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use function array_map;
use function array_merge;
use function array_values;
use function implode;
use function is_array;
use function is_resource;
use function is_string;
use function preg_match;
use Psr\Http\Message\Message_Interface;
use Psr\Http\Message\Stream_Interface;
use function sprintf;
use function str_replace;
use function strtolower;
use function trim;
/**
 * Trait implementing the various methods defined in MessageInterface.
 *
 * @see https://github.com/php-fig/http-message/tree/master/src/MessageInterface.php
 */
trait Message_Trait
{
    /**
     * List of all registered headers, as key => array of values.
     *
     * @var array<non-empty-string, list<string>>
     */
    protected $headers = [];
    /**
     * Map of normalized header name to original name used to register header.
     *
     * @var array<non-empty-string, non-empty-string>
     */
    protected $header_names = [];
    /** @var string */
    private $protocol = '1.1';
    /** @var StreamInterface */
    private $stream;
    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version.
     */
    public function get_protocol_version(): string
    {
        return $this->protocol;
    }
    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new protocol version.
     *
     * @param string $version HTTP protocol version
     * @return static
     */
    public function with_protocol_version(string $version): Message_Interface
    {
        $this->validate_protocol_version($version);
        $new = clone $this;
        $new->protocol = $version;
        return $new;
    }
    /**
     * Retrieves all message headers.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     *     // Represent the headers as a string
     *     foreach ($message->getHeaders() as $name => $values) {
     *         echo $name . ": " . implode(", ", $values);
     *     }
     *
     *     // Emit headers iteratively:
     *     foreach ($message->getHeaders() as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false);
     *         }
     *     }
     *
     * @return array Returns an associative array of the message's headers. Each
     *     key MUST be a header name, and each value MUST be an array of strings.
     * @psalm-return array<non-empty-string, list<string>>
     */
    public function get_headers(): array
    {
        return $this->headers;
    }
    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name Case-insensitive header name.
     * @return bool Returns true if any header names match the given header
     *     name using a case-insensitive string comparison. Returns false if
     *     no matching header name is found in the message.
     */
    public function has_header(string $name): bool
    {
        return isset($this->header_names[strtolower($name)]);
    }
    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $name Case-insensitive header field name.
     * @return string[] An array of string values as provided for the given
     *    header. If the header does not appear in the message, this method MUST
     *    return an empty array.
     */
    public function get_header(string $name): array
    {
        if (!$this->has_header($name)) {
            return [];
        }
        /** @psalm-suppress PossiblyInvalidArrayOffset */
        $name = $this->header_names[strtolower($name)];
        return $this->headers[$name];
    }
    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name Case-insensitive header field name.
     * @return string A string of values as provided for the given header
     *    concatenated together using a comma. If the header does not appear in
     *    the message, this method MUST return an empty string.
     */
    public function get_header_line(string $name): string
    {
        $value = $this->get_header($name);
        if (empty($value)) {
            return '';
        }
        return implode(',', $value);
    }
    /**
     * Return an instance with the provided header, replacing any existing
     * values of any headers with the same case-insensitive name.
     *
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new and/or updated header and value.
     *
     * @param string $name Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     * @return static
     * @throws Exception\InvalidArgumentException For invalid header names or values.
     */
    public function with_header(string $name, $value): Message_Interface
    {
        $this->assert_header($name);
        $normalized = strtolower($name);
        $new = clone $this;
        if ($new->has_header($name)) {
            unset($new->headers[$new->header_names[$normalized]]);
        }
        $value = $this->filter_header_value($value);
        $new->header_names[$normalized] = $name;
        $new->headers[$name] = $value;
        return $new;
    }
    /**
     * Return an instance with the specified header appended with the
     * given value.
     *
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new header and/or value.
     *
     * @param string $name Case-insensitive header field name to add.
     * @param string|string[] $value Header value(s).
     * @return static
     * @throws Exception\InvalidArgumentException For invalid header names or values.
     */
    public function with_added_header(string $name, $value): Message_Interface
    {
        $this->assert_header($name);
        if (!$this->has_header($name)) {
            return $this->with_header($name, $value);
        }
        $header = $this->header_names[strtolower($name)];
        $new = clone $this;
        $value = $this->filter_header_value($value);
        $new->headers[$header] = array_merge($this->headers[$header], $value);
        return $new;
    }
    /**
     * Return an instance without the specified header.
     *
     * Header resolution MUST be done without case-sensitivity.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the named header.
     *
     * @param string $name Case-insensitive header field name to remove.
     * @return static
     */
    public function without_header(string $name): Message_Interface
    {
        if ($name === '' || !$this->has_header($name)) {
            return clone $this;
        }
        $normalized = strtolower($name);
        $original = $this->header_names[$normalized];
        $new = clone $this;
        unset($new->headers[$original], $new->header_names[$normalized]);
        return $new;
    }
    /**
     * Gets the body of the message.
     *
     * @return StreamInterface Returns the body as a stream.
     */
    public function get_body(): Stream_Interface
    {
        return $this->stream;
    }
    /**
     * Return an instance with the specified message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * new body stream.
     *
     * @param StreamInterface $body Body.
     * @return static
     * @throws Exception\InvalidArgumentException When the body is not valid.
     */
    public function with_body(Stream_Interface $body): Message_Interface
    {
        $new = clone $this;
        $new->stream = $body;
        return $new;
    }
    /** @param StreamInterface|string|resource $stream */
    private function get_stream($stream, string $mode_if_not_instance): Stream_Interface
    {
        if ($stream instanceof Stream_Interface) {
            return $stream;
        }
        /** @psalm-suppress DocblockTypeContradiction */
        if (!is_string($stream) && !is_resource($stream)) {
            throw new Exception\InvalidArgumentException('Stream must be a string stream resource identifier, ' . 'an actual stream resource, ' . 'or a Psr\Http\Message\StreamInterface implementation');
        }
        return new Stream($stream, $mode_if_not_instance);
    }
    /**
     * Filter a set of headers to ensure they are in the correct internal format.
     *
     * Used by message constructors to allow setting all initial headers at once.
     *
     * @param array<non-empty-string, string|string[]> $originalHeaders Headers to filter.
     */
    private function set_headers(array $original_headers): void
    {
        $header_names = $headers = [];
        foreach ($original_headers as $header => $value) {
            $value = $this->filter_header_value($value);
            $this->assert_header($header);
            $header_names[strtolower($header)] = $header;
            $headers[$header] = $value;
        }
        $this->header_names = $header_names;
        $this->headers = $headers;
    }
    /**
     * Validate the HTTP protocol version
     *
     * @throws Exception\InvalidArgumentException On invalid HTTP protocol version.
     */
    private function validate_protocol_version(string $version): void
    {
        if (empty($version)) {
            throw new Exception\InvalidArgumentException('HTTP protocol version can not be empty');
        }
        // HTTP/1 uses a "<major>.<minor>" numbering scheme to indicate
        // versions of the protocol, while HTTP/2 does not.
        if (!preg_match('#^(1\.[01]|2(\.0)?)$#', $version)) {
            throw new Exception\InvalidArgumentException(sprintf('Unsupported HTTP protocol version "%s" provided', $version));
        }
    }
    /** @return list<string> */
    private function filter_header_value(mixed $values): array
    {
        if (!is_array($values)) {
            $values = [$values];
        }
        if ([] === $values) {
            throw new Exception\InvalidArgumentException('Invalid header value: must be a string or array of strings; ' . 'cannot be an empty array');
        }
        return array_map(static function ($value): string {
            Header_Security::assert_valid($value);
            $value = (string) $value;
            // Normalize line folding to a single space (RFC 7230#3.2.4).
            $value = str_replace(["\r\n\t", "\r\n "], ' ', $value);
            // Remove optional whitespace (OWS, RFC 7230#3.2.3) around the header value.
            return trim($value, "\t ");
        }, array_values($values));
    }
    /**
     * Ensure header name and values are valid.
     *
     * @psalm-assert non-empty-string $name
     * @throws Exception\InvalidArgumentException
     */
    private function assert_header(mixed $name): void
    {
        Header_Security::assert_valid_name($name);
    }
}