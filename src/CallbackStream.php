<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use function array_key_exists;
use Override;
use Psr\Http\Message\Stream_Interface;
use const SEEK_SET;
use Stringable;
/**
 * Implementation of PSR HTTP streams
 */
class Callback_Stream implements Stream_Interface, Stringable
{
    /** @var callable|null */
    protected $callback;
    /**
     * @throws Exception\InvalidArgumentException
     */
    public function __construct(callable $callback)
    {
        $this->attach($callback);
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function __toString(): string
    {
        return $this->get_contents();
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function close(): void
    {
        $this->callback = null;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function detach(): ?callable
    {
        $callback = $this->callback;
        $this->callback = null;
        return $callback;
    }
    /**
     * Attach a new callback to the instance.
     */
    public function attach(callable $callback): void
    {
        $this->callback = $callback;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_size(): ?int
    {
        return null;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function tell(): int
    {
        throw Exception\Untellable_Stream_Exception::for_callback_stream();
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function eof(): bool
    {
        return $this->callback === null;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function is_seekable(): bool
    {
        return false;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw Exception\Unseekable_Stream_Exception::for_callback_stream();
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function rewind(): void
    {
        throw Exception\Unrewindable_Stream_Exception::for_callback_stream();
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function is_writable(): bool
    {
        return false;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function write(string $string): int
    {
        throw Exception\Unwritable_Stream_Exception::for_callback_stream();
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function is_readable(): bool
    {
        return false;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function read(int $length): string
    {
        throw Exception\Unreadable_Stream_Exception::for_callback_stream();
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_contents(): string
    {
        $callback = $this->detach();
        return $callback !== null ? (string) $callback() : '';
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_metadata(?string $key = null): array|null|bool|string
    {
        $metadata = ['eof' => $this->eof(), 'stream_type' => 'callback', 'seekable' => false];
        if (null === $key) {
            return $metadata;
        }
        if (!array_key_exists($key, $metadata)) {
            return null;
        }
        return $metadata[$key];
    }
}