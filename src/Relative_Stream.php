<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use Override;
use Psr\Http\Message\Stream_Interface;
use const SEEK_SET;
use Stringable;
/**
 * Wrapper for default Stream class, representing subpart (starting from given offset) of initial stream.
 * It can be used to avoid copying full stream, conserving memory.
 *
 * @see AbstractSerializer::splitStream()
 */
final readonly class Relative_Stream implements Stream_Interface, Stringable
{
    private int $offset;
    public function __construct(private Stream_Interface $decorated_stream, ?int $offset)
    {
        $this->offset = (int) $offset;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function __toString(): string
    {
        if ($this->is_seekable()) {
            $this->seek(0);
        }
        return $this->get_contents();
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function close(): void
    {
        $this->decorated_stream->close();
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function detach()
    {
        return $this->decorated_stream->detach();
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_size(): ?int
    {
        $size = $this->decorated_stream->get_size();
        if ($size === null) {
            return null;
        }
        return $size - $this->offset;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function tell(): int
    {
        return $this->decorated_stream->tell() - $this->offset;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function eof(): bool
    {
        return $this->decorated_stream->eof();
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function is_seekable(): bool
    {
        return $this->decorated_stream->is_seekable();
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($whence === SEEK_SET) {
            $this->decorated_stream->seek($offset + $this->offset, $whence);
            return;
        }
        $this->decorated_stream->seek($offset, $whence);
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
        return $this->decorated_stream->is_writable();
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function write(string $string): int
    {
        if ($this->tell() < 0) {
            throw new Exception\Invalid_Stream_Pointer_Position_Exception();
        }
        return $this->decorated_stream->write($string);
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function is_readable(): bool
    {
        return $this->decorated_stream->is_readable();
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function read(int $length): string
    {
        if ($this->tell() < 0) {
            throw new Exception\Invalid_Stream_Pointer_Position_Exception();
        }
        return $this->decorated_stream->read($length);
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_contents(): string
    {
        if ($this->tell() < 0) {
            throw new Exception\Invalid_Stream_Pointer_Position_Exception();
        }
        return $this->decorated_stream->get_contents();
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_metadata(?string $key = null)
    {
        return $this->decorated_stream->get_metadata($key);
    }
}