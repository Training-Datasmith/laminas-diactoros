<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use function array_key_exists;
use function gettype;
use function is_array;
use function is_object;
use Override;
use Psr\Http\Message\Server_Request_Interface;
use Psr\Http\Message\Stream_Interface;
use Psr\Http\Message\Uploaded_File_Interface;
use Psr\Http\Message\Uri_Interface;
use function sprintf;
/**
 * Server-side HTTP request
 *
 * Extends the Request definition to add methods for accessing incoming data,
 * specifically server parameters, cookies, matched path parameters, query
 * string arguments, body parameters, and upload file information.
 *
 * "Attributes" are discovered via decomposing the request (and usually
 * specifically the URI path), and typically will be injected by the application.
 *
 * Requests are considered immutable; all methods that might change state are
 * implemented such that they retain the internal state of the current
 * message and return a new instance that contains the changed state.
 */
class Server_Request implements Server_Request_Interface
{
    use Request_Trait;
    private array $attributes = [];
    private array $uploaded_files;
    /**
     * @param array $serverParams Server parameters, typically from $_SERVER
     * @param array $uploadedFiles Upload file information, a tree of UploadedFiles
     * @param null|string|UriInterface $uri URI for the request, if any.
     * @param null|string $method HTTP method for the request, if any.
     * @param string|resource|StreamInterface $body Message body, if any.
     * @param array<non-empty-string, string|string[]> $headers Headers for the message, if any.
     * @param array $cookieParams Cookies for the message, if any.
     * @param array $queryParams Query params for the message, if any.
     * @param null|array|object $parsedBody The deserialized body parameters, if any.
     * @param string $protocol HTTP protocol version.
     * @throws Exception\InvalidArgumentException For any invalid value.
     */
    public function __construct(private array $server_params = [], array $uploaded_files = [], null|string|Uri_Interface $uri = null, ?string $method = null, $body = 'php://input', array $headers = [], private array $cookie_params = [], private array $query_params = [], private $parsed_body = null, string $protocol = '1.1')
    {
        $this->validate_uploaded_files($uploaded_files);
        if ($body === 'php://input') {
            $body = new Stream($body, 'r');
        }
        $this->initialize($uri, $method, $body, $headers);
        $this->uploaded_files = $uploaded_files;
        $this->protocol = $protocol;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_server_params(): array
    {
        return $this->server_params;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_uploaded_files(): array
    {
        return $this->uploaded_files;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function with_uploaded_files(array $uploaded_files): Server_Request
    {
        $this->validate_uploaded_files($uploaded_files);
        $new = clone $this;
        $new->uploaded_files = $uploaded_files;
        return $new;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_cookie_params(): array
    {
        return $this->cookie_params;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function with_cookie_params(array $cookies): Server_Request
    {
        $new = clone $this;
        $new->cookie_params = $cookies;
        return $new;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_query_params(): array
    {
        return $this->query_params;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function with_query_params(array $query): Server_Request
    {
        $new = clone $this;
        $new->query_params = $query;
        return $new;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_parsed_body()
    {
        return $this->parsed_body;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function with_parsed_body($data): Server_Request
    {
        /** @psalm-suppress DocblockTypeContradiction */
        if (!is_array($data) && !is_object($data) && null !== $data) {
            throw new Exception\InvalidArgumentException(sprintf('%s expects a null, array, or object argument; received %s', __METHOD__, gettype($data)));
        }
        $new = clone $this;
        $new->parsed_body = $data;
        return $new;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_attributes(): array
    {
        return $this->attributes;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_attribute(string $name, $default = null)
    {
        if (!array_key_exists($name, $this->attributes)) {
            return $default;
        }
        return $this->attributes[$name];
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function with_attribute(string $name, $value): Server_Request
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function without_attribute(string $name): Server_Request
    {
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }
    /**
     * Recursively validate the structure in an uploaded files array.
     *
     * @throws Exception\InvalidArgumentException If any leaf is not an UploadedFileInterface instance.
     */
    private function validate_uploaded_files(array $uploaded_files): void
    {
        foreach ($uploaded_files as $file) {
            if (is_array($file)) {
                $this->validate_uploaded_files($file);
                continue;
            }
            if (!$file instanceof Uploaded_File_Interface) {
                throw new Exception\InvalidArgumentException('Invalid leaf in uploaded files structure');
            }
        }
    }
}