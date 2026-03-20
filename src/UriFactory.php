<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use function array_change_key_case;
use function array_key_exists;
use function assert;
use const CASE_LOWER;
use function count;
use function explode;
use function gettype;
use function implode;
use function is_bool;
use function is_scalar;
use function is_string;
use function ltrim;
use Override;
use function preg_match;
use function preg_replace;
use Psr\Http\Message\Uri_Factory_Interface;
use Psr\Http\Message\Uri_Interface;
use function sprintf;
use function str_contains;
use function strlen;
use function strrpos;
use function strtolower;
use function substr;
class Uri_Factory implements Uri_Factory_Interface
{
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function create_uri(string $uri = ''): Uri_Interface
    {
        return new Uri($uri);
    }
    /**
     * Create a Uri instance based on the headers and $_SERVER data.
     *
     * @param array<non-empty-string, list<string>|int|float|string> $server SAPI parameters
     * @param array<string, string|list<string>> $headers
     */
    public static function create_from_sapi(array $server, array $headers): Uri
    {
        $uri = new Uri('');
        $is_https = false;
        if (array_key_exists('HTTPS', $server)) {
            $is_https = self::marshal_https_value($server['HTTPS']);
        } elseif (array_key_exists('https', $server)) {
            $is_https = self::marshal_https_value($server['https']);
        }
        $uri = $uri->with_scheme($is_https ? 'https' : 'http');
        [$host, $port] = self::marshal_host_and_port($server, $headers);
        if (!empty($host)) {
            $uri = $uri->with_host($host);
            if ($port !== null) {
                $uri = $uri->with_port($port);
            }
        }
        $path = self::marshal_request_path($server);
        // Strip query string
        $path = explode('?', $path, 2)[0];
        $query = '';
        if (isset($server['QUERY_STRING']) && is_scalar($server['QUERY_STRING'])) {
            $query = ltrim((string) $server['QUERY_STRING'], '?');
        }
        $fragment = '';
        if (str_contains($path, '#')) {
            $parts = explode('#', $path, 2);
            assert(count($parts) >= 2);
            [$path, $fragment] = $parts;
        }
        return $uri->with_path($path)->with_fragment($fragment)->with_query($query);
    }
    /**
     * Retrieve a header value from an array of headers using a case-insensitive lookup.
     *
     * @template T
     * @param array<string, string|list<string>> $headers Key/value header pairs
     * @param T $default Default value to return if header not found
     * @return string|T
     */
    private static function get_header_from_array(string $name, array $headers, $default = null)
    {
        $header = strtolower($name);
        $headers = array_change_key_case($headers, CASE_LOWER);
        if (!array_key_exists($header, $headers)) {
            return $default;
        }
        if (is_string($headers[$header])) {
            return $headers[$header];
        }
        return implode(', ', $headers[$header]);
    }
    /**
     * Marshal the host and port from the PHP environment.
     *
     * @param array<string, string|list<string>> $headers
     * @return array{0:string, 1:int|null} Array of two items, host and port,
     *     in that order (can be passed to a list() operation).
     */
    private static function marshal_host_and_port(array $server, array $headers): array
    {
        /** @var array{string, null} $defaults */
        static $defaults = ['', null];
        $host = self::get_header_from_array('host', $headers, false);
        if ($host !== false) {
            // Ignore obviously malformed host headers:
            // - Whitespace is invalid within a hostname and break the URI representation within HTTP.
            //   non-printable characters other than SPACE and TAB are already rejected by HeaderSecurity.
            // - A comma indicates that multiple host headers have been sent which is not legal
            //   and might be used in an attack where a load balancer sees a different host header
            //   than Diactoros.
            if (!preg_match('/[\t ,]/', $host)) {
                return self::marshal_host_and_port_from_header($host);
            }
        }
        if (!isset($server['SERVER_NAME'])) {
            return $defaults;
        }
        $host = (string) $server['SERVER_NAME'];
        $port = isset($server['SERVER_PORT']) ? (int) $server['SERVER_PORT'] : null;
        if (!isset($server['SERVER_ADDR']) || !preg_match('/^\[[0-9a-fA-F\:]+\]$/', $host)) {
            return [$host, $port];
        }
        // Misinterpreted IPv6-Address
        // Reported for Safari on Windows
        return self::marshal_ipv6host_and_port($server, $port);
    }
    /**
     * @return array{string, int|null} Array of two items, host and port,
     *     in that order (can be passed to a list() operation).
     */
    private static function marshal_ipv6host_and_port(array $server, ?int $port): array
    {
        $host = '[' . $server['SERVER_ADDR'] . ']';
        $port ??= 80;
        $port_separator_pos = strrpos($host, ':');
        if (false === $port_separator_pos) {
            return [$host, $port];
        }
        if ($port . ']' === substr($host, $port_separator_pos + 1)) {
            // The last digit of the IPv6-Address has been taken as port
            // Unset the port so the default port can be used
            $port = null;
        }
        return [$host, $port];
    }
    /**
     * Detect the path for the request
     *
     * Looks at a variety of criteria in order to attempt to autodetect the base
     * request path, including:
     *
     * - IIS7 UrlRewrite environment
     * - REQUEST_URI
     * - ORIG_PATH_INFO
     */
    private static function marshal_request_path(array $server): string
    {
        // IIS7 with URL Rewrite: make sure we get the unencoded url
        // (double slash problem).
        /** @var string|array<string>|null $iisUrlRewritten */
        $iis_url_rewritten = $server['IIS_WasUrlRewritten'] ?? null;
        /** @var string|array<string> $unencodedUrl */
        $unencoded_url = $server['UNENCODED_URL'] ?? '';
        if ('1' === $iis_url_rewritten && is_string($unencoded_url) && '' !== $unencoded_url) {
            return $unencoded_url;
        }
        /** @var string|array<string>|null $requestUri */
        $request_uri = $server['REQUEST_URI'] ?? null;
        if (is_string($request_uri)) {
            $result = preg_replace('#^[^/:]+://[^/]+#', '', $request_uri);
            assert($result !== null, 'Always true condition for psalm type safety');
            return $result;
        }
        $orig_path_info = $server['ORIG_PATH_INFO'] ?? '';
        if (!is_string($orig_path_info) || '' === $orig_path_info) {
            return '/';
        }
        return $orig_path_info;
    }
    private static function marshal_https_value(mixed $https): bool
    {
        if (is_bool($https)) {
            return $https;
        }
        if (!is_string($https)) {
            throw new Exception\InvalidArgumentException(sprintf('SAPI HTTPS value MUST be a string or boolean; received %s', gettype($https)));
        }
        return 'on' === strtolower($https);
    }
    /**
     * @internal
     *
     * @return array{string, int|null} Array of two items, host and port, in that order (can be
     *     passed to a list() operation).
     * @psalm-mutation-free
     */
    public static function marshal_host_and_port_from_header(string $host): array
    {
        $port = null;
        // works for regname, IPv4 & IPv6
        if (preg_match('|\:(\d+)$|', $host, $matches)) {
            $host = substr($host, 0, -1 * (strlen($matches[1]) + 1));
            $port = (int) $matches[1];
        }
        return [$host, $port];
    }
}