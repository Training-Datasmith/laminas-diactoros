<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use Override;
use Psr\Http\Message\Request_Interface;
use Psr\Http\Message\Stream_Interface;
use Psr\Http\Message\Uri_Interface;
use function strtolower;
/**
 * HTTP Request encapsulation
 *
 * Requests are considered immutable; all methods that might change state are
 * implemented such that they retain the internal state of the current
 * message and return a new instance that contains the changed state.
 */
class Request implements Request_Interface
{
    use Request_Trait;
    /**
     * @param null|string|UriInterface $uri URI for the request, if any.
     * @param null|string $method HTTP method for the request, if any.
     * @param string|resource|StreamInterface $body Message body, if any.
     * @param array<non-empty-string, string|string[]> $headers Headers for the message, if any.
     * @throws Exception\InvalidArgumentException For any invalid value.
     */
    public function __construct($uri = null, ?string $method = null, $body = 'php://temp', array $headers = [])
    {
        $this->initialize($uri, $method, $body, $headers);
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_headers(): array
    {
        $headers = $this->headers;
        if (!$this->has_header('host') && $this->uri->get_host()) {
            $headers['Host'] = [$this->get_host_from_uri()];
        }
        return $headers;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_header(string $name): array
    {
        if (empty($name) || !$this->has_header($name)) {
            if (strtolower($name) === 'host' && $this->uri->get_host()) {
                return [$this->get_host_from_uri()];
            }
            return [];
        }
        $header = $this->header_names[strtolower($name)];
        return $this->headers[$header];
    }
}