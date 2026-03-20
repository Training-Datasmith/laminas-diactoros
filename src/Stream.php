<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use function array_key_exists;
use function assert;
use function fclose;
use function feof;
use function fopen;
use function fread;
use function fseek;
use function fstat;
use function ftell;
use function fwrite;
use function get_resource_type;
use function in_array;
use function is_int;
use function is_resource;
use function is_string;
use Override;
use Psr\Http\Message\Stream_Interface;
use RuntimeException;
use const SEEK_SET;
use function sprintf;
use function str_contains;
use function stream_get_contents;
use function stream_get_meta_data;
use Stringable;
use Throwable;
/**
 * Implementation of PSR HTTP streams
 */
class Stream implements Stream_Interface, Stringable
{
    /**
     * A list of allowed stream resource types that are allowed to instantiate a Stream
     */
    private const ALLOWED_STREAM_RESOURCE_TYPES = ['stream'];
    /** @var resource|null */
    protected $resource;
    /** @var string|object|resource|null */
    protected $stream;
    /**
     * @param string|object|resource $stream
     * @param string $mode Mode with which to open stream
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($stream, string $mode = 'r')
    {
        $this->set_stream($stream, $mode);
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function __toString(): string
    {
        if (!$this->is_readable()) {
            return '';
        }
        try {
            if ($this->is_seekable()) {
                $this->rewind();
            }
            return $this->get_contents();
        } catch (RuntimeException) {
            return '';
        }
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function close(): void
    {
        if (!$this->resource) {
            return;
        }
        $resource = $this->detach();
        assert(is_resource($resource), 'Always true condition for psalm type safety');
        fclose($resource);
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;
        return $resource;
    }
    /**
     * Attach a new stream/resource to the instance.
     *
     * @param string|object|resource $resource
     * @throws Exception\InvalidArgumentException For stream identifier that cannot be cast to a resource.
     * @throws Exception\InvalidArgumentException For non-resource stream.
     */
    public function attach($resource, string $mode = 'r'): void
    {
        $this->set_stream($resource, $mode);
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_size(): ?int
    {
        if (null === $this->resource) {
            return null;
        }
        $stats = fstat($this->resource);
        if ($stats !== false) {
            return $stats['size'];
        }
        return null;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function tell(): int
    {
        if (!$this->resource) {
            throw Exception\Untellable_Stream_Exception::due_to_missing_resource();
        }
        $result = ftell($this->resource);
        if (!is_int($result)) {
            throw Exception\Untellable_Stream_Exception::due_to_php_error();
        }
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function eof(): bool
    {
        if (!$this->resource) {
            return true;
        }
        return feof($this->resource);
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function is_seekable(): bool
    {
        if (!$this->resource) {
            return false;
        }
        $meta = stream_get_meta_data($this->resource);
        return $meta['seekable'];
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->resource) {
            throw Exception\Unseekable_Stream_Exception::due_to_missing_resource();
        }
        if (!$this->is_seekable()) {
            throw Exception\Unseekable_Stream_Exception::due_to_configuration();
        }
        $result = fseek($this->resource, $offset, $whence);
        if (0 !== $result) {
            throw Exception\Unseekable_Stream_Exception::due_to_php_error();
        }
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function rewind(): void
    {
        $this->seek(0);
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function is_writable(): bool
    {
        if (!$this->resource) {
            return false;
        }
        $meta = stream_get_meta_data($this->resource);
        $mode = $meta['mode'];
        return str_contains($mode, 'x') || str_contains($mode, 'w') || str_contains($mode, 'c') || str_contains($mode, 'a') || str_contains($mode, '+');
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function write($string): int
    {
        if (!$this->resource) {
            throw Exception\Unwritable_Stream_Exception::due_to_missing_resource();
        }
        if (!$this->is_writable()) {
            throw Exception\Unwritable_Stream_Exception::due_to_configuration();
        }
        $result = fwrite($this->resource, (string) $string);
        if (false === $result) {
            throw Exception\Unwritable_Stream_Exception::due_to_php_error();
        }
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function is_readable(): bool
    {
        if (!$this->resource) {
            return false;
        }
        $meta = stream_get_meta_data($this->resource);
        $mode = $meta['mode'];
        return str_contains($mode, 'r') || str_contains($mode, '+');
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function read(int $length): string
    {
        if (!$this->resource) {
            throw Exception\Unreadable_Stream_Exception::due_to_missing_resource();
        }
        if (!$this->is_readable()) {
            throw Exception\Unreadable_Stream_Exception::due_to_configuration();
        }
        $result = fread($this->resource, $length);
        if (false === $result) {
            throw Exception\Unreadable_Stream_Exception::due_to_php_error();
        }
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_contents(): string
    {
        if (!$this->is_readable()) {
            throw Exception\Unreadable_Stream_Exception::due_to_configuration();
        }
        assert($this->resource !== null, 'Always true condition for psalm type safety');
        $result = stream_get_contents($this->resource);
        if (false === $result) {
            throw Exception\Unreadable_Stream_Exception::due_to_php_error();
        }
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_metadata(?string $key = null)
    {
        $metadata = [];
        if (null !== $this->resource) {
            $metadata = stream_get_meta_data($this->resource);
        }
        if (null === $key) {
            return $metadata;
        }
        if (!array_key_exists($key, $metadata)) {
            return null;
        }
        return $metadata[$key];
    }
    /**
     * Set the internal stream resource.
     *
     * @param string|object|resource $stream String stream target or stream resource.
     * @param string $mode Resource mode for stream target.
     * @throws Exception\InvalidArgumentException For invalid streams or resources.
     */
    private function set_stream($stream, string $mode = 'r'): void
    {
        $error = null;
        $resource = $stream;
        if (is_string($stream)) {
            try {
                $resource = fopen($stream, $mode);
            } catch (Throwable $error) {
            }
            if (!is_resource($resource)) {
                throw new Exception\RuntimeException(sprintf('Empty or non-existent stream identifier or file path provided: "%s"', $stream), 0, $error);
            }
        }
        if (!$this->is_valid_stream_resource_type($resource)) {
            throw new Exception\InvalidArgumentException('Invalid stream provided; must be a string stream identifier or stream resource');
        }
        if ($stream !== $resource) {
            $this->stream = $stream;
        }
        $this->resource = $resource;
    }
    /**
     * Determine if a resource is one of the resource types allowed to instantiate a Stream
     *
     * @param mixed $resource Stream resource.
     * @psalm-assert-if-true resource $resource
     */
    private function is_valid_stream_resource_type(mixed $resource): bool
    {
        if (is_resource($resource)) {
            return in_array(get_resource_type($resource), self::ALLOWED_STREAM_RESOURCE_TYPES, true);
        }
        return false;
    }
}