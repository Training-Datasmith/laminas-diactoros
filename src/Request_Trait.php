<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use function array_keys;
use function is_string;
use function preg_match;
use Psr\Http\Message\Request_Interface;
use Psr\Http\Message\Stream_Interface;
use Psr\Http\Message\Uri_Interface;
use function sprintf;
use function strtolower;
/**
 * Trait with common request behaviors.
 *
 * Server and client-side requests differ slightly in how the Host header is
 * handled; on client-side, it should be calculated on-the-fly from the
 * composed URI (if present), while on server-side, it will be calculated from
 * the environment. As such, this trait exists to provide the common code
 * between both client-side and server-side requests, and each can then
 * use the headers functionality required by their implementations.
 */
trait Request_Trait
{
    use Message_Trait;
    /** @var string */
    private $method = 'GET';
    /**
     * The request-target, if it has been provided or calculated.
     *
     * @var null|string
     */
    private $request_target;
    /** @var UriInterface */
    private $uri;
    /**
     * Initialize request state.
     *
     * Used by constructors.
     *
     * @param null|string|UriInterface $uri URI for the request, if any.
     * @param null|string $method HTTP method for the request, if any.
     * @param string|resource|StreamInterface $body Message body, if any.
     * @param array<non-empty-string, string|string[]> $headers Headers for the message, if any.
     * @throws Exception\InvalidArgumentException For any invalid value.
     */
    private function initialize($uri = null, ?string $method = null, $body = 'php://memory', array $headers = []): void
    {
        if ($method !== null) {
            $this->set_method($method);
        }
        $this->uri = $this->create_uri($uri);
        $this->stream = $this->get_stream($body, 'wb+');
        $this->set_headers($headers);
        // per PSR-7: attempt to set the Host header from a provided URI if no
        // Host header is provided
        if (!$this->has_header('Host') && $this->uri->get_host()) {
            $this->header_names['host'] = 'Host';
            $this->headers['Host'] = [$this->get_host_from_uri()];
        }
    }
    /**
     * Create and return a URI instance.
     *
     * If `$uri` is a already a `UriInterface` instance, returns it.
     *
     * If `$uri` is a string, passes it to the `Uri` constructor to return an
     * instance.
     *
     * If `$uri is null, creates and returns an empty `Uri` instance.
     *
     * Otherwise, it raises an exception.
     *
     * @throws Exception\InvalidArgumentException
     */
    private function create_uri(null|string|Uri_Interface $uri): Uri_Interface
    {
        if ($uri instanceof Uri_Interface) {
            return $uri;
        }
        if (is_string($uri)) {
            return new Uri($uri);
        }
        return new Uri();
    }
    /**
     * Retrieves the message's request target.
     *
     * Retrieves the message's request-target either as it will appear (for
     * clients), as it appeared at request (for servers), or as it was
     * specified for the instance (see withRequestTarget()).
     *
     * In most cases, this will be the origin-form of the composed URI,
     * unless a value was provided to the concrete implementation (see
     * withRequestTarget() below).
     *
     * If no URI is available, and no request-target has been specifically
     * provided, this method MUST return the string "/".
     */
    public function get_request_target(): string
    {
        if (null !== $this->request_target) {
            return $this->request_target;
        }
        $target = $this->uri->get_path();
        if ($this->uri->get_query()) {
            $target .= '?' . $this->uri->get_query();
        }
        if (empty($target)) {
            return '/';
        }
        return $target;
    }
    /**
     * Create a new instance with a specific request-target.
     *
     * If the request needs a non-origin-form request-target — e.g., for
     * specifying an absolute-form, authority-form, or asterisk-form —
     * this method may be used to create an instance with the specified
     * request-target, verbatim.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * changed request target.
     *
     * @link http://tools.ietf.org/html/rfc7230#section-2.7 (for the various
     *     request-target forms allowed in request messages)
     *
     * @throws Exception\InvalidArgumentException If the request target is invalid.
     * @return static
     */
    public function with_request_target(string $request_target): Request_Interface
    {
        if (preg_match('#\s#', $request_target)) {
            throw new Exception\InvalidArgumentException('Invalid request target provided; cannot contain whitespace');
        }
        $new = clone $this;
        $new->request_target = $request_target;
        return $new;
    }
    /**
     * Retrieves the HTTP method of the request.
     *
     * @return string Returns the request method.
     */
    public function get_method(): string
    {
        return $this->method;
    }
    /**
     * Return an instance with the provided HTTP method.
     *
     * While HTTP method names are typically all uppercase characters, HTTP
     * method names are case-sensitive and thus implementations SHOULD NOT
     * modify the given string.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request method.
     *
     * @param string $method Case-insensitive method.
     * @throws Exception\InvalidArgumentException For invalid HTTP methods.
     * @return static
     */
    public function with_method(string $method): Request_Interface
    {
        $new = clone $this;
        $new->set_method($method);
        return $new;
    }
    /**
     * Retrieves the URI instance.
     *
     * This method MUST return a UriInterface instance.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     *
     * @return UriInterface Returns a UriInterface instance
     *     representing the URI of the request, if any.
     */
    public function get_uri(): Uri_Interface
    {
        return $this->uri;
    }
    /**
     * Returns an instance with the provided URI.
     *
     * This method will update the Host header of the returned request by
     * default if the URI contains a host component. If the URI does not
     * contain a host component, any pre-existing Host header will be carried
     * over to the returned request.
     *
     * You can opt-in to preserving the original state of the Host header by
     * setting `$preserveHost` to `true`. When `$preserveHost` is set to
     * `true`, the returned request will not update the Host header of the
     * returned message -- even if the message contains no Host header. This
     * means that a call to `getHeader('Host')` on the original request MUST
     * equal the return value of a call to `getHeader('Host')` on the returned
     * request.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new UriInterface instance.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     *
     * @param UriInterface $uri New request URI to use.
     * @param bool $preserveHost Preserve the original state of the Host header.
     * @return static
     */
    public function with_uri(Uri_Interface $uri, bool $preserve_host = false): Request_Interface
    {
        $new = clone $this;
        $new->uri = $uri;
        if ($preserve_host && $this->has_header('Host')) {
            return $new;
        }
        if (!$uri->get_host()) {
            return $new;
        }
        $host = $uri->get_host();
        if ($uri->get_port() !== null) {
            $host .= ':' . $uri->get_port();
        }
        $new->header_names['host'] = 'Host';
        // Remove an existing host header if present, regardless of current
        // de-normalization of the header name.
        // @see https://github.com/zendframework/zend-diactoros/issues/91
        foreach (array_keys($new->headers) as $header) {
            if (strtolower((string) $header) === 'host') {
                unset($new->headers[$header]);
            }
        }
        $new->headers['Host'] = [$host];
        return $new;
    }
    /**
     * Set and validate the HTTP method
     *
     * @throws Exception\InvalidArgumentException On invalid HTTP method.
     */
    private function set_method(string $method): void
    {
        if (!preg_match('/^[!#$%&\'*+.^_`\|~0-9a-z-]+$/i', $method)) {
            throw new Exception\InvalidArgumentException(sprintf('Unsupported HTTP method "%s" provided', $method));
        }
        $this->method = $method;
    }
    /**
     * Retrieve the host from the URI instance
     */
    private function get_host_from_uri(): string
    {
        $host = $this->uri->get_host();
        $host .= $this->uri->get_port() !== null ? ':' . $this->uri->get_port() : '';
        return $host;
    }
}