<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Server_Request_Filter;

use function array_values;
use function assert;
use function count;
use function explode;
use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;
use function filter_var;
use function in_array;
use function is_string;
use Laminas\Diactoros\Exception\Invalid_Forwarded_Header_Name_Exception;
use Laminas\Diactoros\Exception\Invalid_Proxy_Address_Exception;
use Laminas\Diactoros\Uri_Factory;
use Override;
use Psr\Http\Message\Server_Request_Interface;
use function str_contains;
use function strtolower;
/**
 * Modify the URI to reflect the X-Forwarded-* headers.
 *
 * If the request comes from a trusted proxy, this filter will analyze the
 * various X-Forwarded-* headers, if any, and if they are marked as trusted,
 * in order to return a new request that composes a URI instance that reflects
 * those headers.
 */
final readonly class Filter_Using_X_Forwarded_Headers implements Filter_Server_Request_Interface
{
    public const HEADER_HOST = 'X-FORWARDED-HOST';
    public const HEADER_PORT = 'X-FORWARDED-PORT';
    public const HEADER_PROTO = 'X-FORWARDED-PROTO';
    private const X_FORWARDED_HEADERS = [self::HEADER_HOST, self::HEADER_PORT, self::HEADER_PROTO];
    /**
     * Only allow construction via named constructors
     *
     * @param list<non-empty-string> $trustedProxies
     * @param list<FilterUsingXForwardedHeaders::HEADER_*> $trustedHeaders
     */
    private function __construct(private array $trusted_proxies = [], private array $trusted_headers = [])
    {
    }
    #[Override]
    public function __invoke(Server_Request_Interface $request): Server_Request_Interface
    {
        $remote_address = $request->get_server_params()['REMOTE_ADDR'] ?? '';
        if ('' === $remote_address || !is_string($remote_address)) {
            // Should we trigger a warning here?
            return $request;
        }
        if (!$this->is_from_trusted_proxy($remote_address)) {
            // Do nothing
            return $request;
        }
        // Update the URI based on the trusted headers
        $uri = $original_uri = $request->get_uri();
        foreach ($this->trusted_headers as $header_name) {
            $header = $request->get_header_line($header_name);
            if ('' === $header) {
                // Reject empty headers and/or headers with multiple values
                continue;
            }
            if (str_contains($header, ',')) {
                // Reject empty headers and/or headers with multiple values
                continue;
            }
            switch ($header_name) {
                case self::HEADER_HOST:
                    [$host, $port] = Uri_Factory::marshal_host_and_port_from_header($header);
                    $uri = $uri->with_host($host);
                    if ($port !== null) {
                        $uri = $uri->with_port($port);
                    }
                    break;
                case self::HEADER_PORT:
                    $uri = $uri->with_port((int) $header);
                    break;
                case self::HEADER_PROTO:
                    $scheme = strtolower($header) === 'https' ? 'https' : 'http';
                    $uri = $uri->with_scheme($scheme);
                    break;
            }
        }
        if ($uri !== $original_uri) {
            return $request->with_uri($uri);
        }
        return $request;
    }
    /**
     * Indicate which proxies and which X-Forwarded headers to trust.
     *
     * @param list<non-empty-string> $proxyCIDRList Each element may
     *     be an IP address or a subnet specified using CIDR notation; both IPv4
     *     and IPv6 are supported. The special string "*" will be translated to
     *     two entries, "0.0.0.0/0" and "::/0". An empty list indicates no
     *     proxies are trusted.
     * @param list<FilterUsingXForwardedHeaders::HEADER_*> $trustedHeaders If
     *     the list is empty, all X-Forwarded headers are trusted.
     * @throws InvalidProxyAddressException
     * @throws InvalidForwardedHeaderNameException
     */
    public static function trust_proxies(array $proxy_cidr_list, array $trusted_headers = self::X_FORWARDED_HEADERS): self
    {
        $proxy_cidr_list = self::normalize_proxies_list($proxy_cidr_list);
        self::validate_trusted_headers($trusted_headers);
        return new self($proxy_cidr_list, $trusted_headers);
    }
    /**
     * Trust any X-FORWARDED-* headers from any address.
     *
     * This is functionally equivalent to calling `trustProxies(['*'])`.
     *
     * WARNING: Only do this if you know for certain that your application
     * sits behind a trusted proxy that cannot be spoofed. This should only
     * be the case if your server is not publicly addressable, and all requests
     * are routed via a reverse proxy (e.g., a load balancer, a server such as
     * Caddy, when using Traefik, etc.).
     */
    public static function trust_any(): self
    {
        return self::trust_proxies(['*']);
    }
    /**
     * Trust X-Forwarded headers from reserved subnetworks.
     *
     * This is functionally equivalent to calling `trustProxies()` where the
     * `$proxcyCIDRList` argument is a list with the following:
     *
     * - 10.0.0.0/8
     * - 127.0.0.0/8
     * - 172.16.0.0/12
     * - 192.168.0.0/16
     * - ::1/128 (IPv6 localhost)
     * - fc00::/7 (IPv6 private networks)
     * - fe80::/10 (IPv6 local-link addresses)
     *
     * @param list<FilterUsingXForwardedHeaders::HEADER_*> $trustedHeaders If
     *     the list is empty, all X-Forwarded headers are trusted.
     * @throws InvalidForwardedHeaderNameException
     */
    public static function trust_reserved_subnets(array $trusted_headers = self::X_FORWARDED_HEADERS): self
    {
        return self::trust_proxies([
            '10.0.0.0/8',
            '127.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '::1/128',
            // ipv6 localhost
            'fc00::/7',
            // ipv6 private networks
            'fe80::/10',
        ], $trusted_headers);
    }
    private function is_from_trusted_proxy(string $remote_address): bool
    {
        foreach ($this->trusted_proxies as $proxy) {
            if (Ip_Range::matches($remote_address, $proxy)) {
                return true;
            }
        }
        return false;
    }
    /** @throws InvalidForwardedHeaderNameException */
    private static function validate_trusted_headers(array $headers): void
    {
        foreach ($headers as $header) {
            if (!in_array($header, self::X_FORWARDED_HEADERS, true)) {
                throw Invalid_Forwarded_Header_Name_Exception::for_header($header);
            }
        }
    }
    /**
     * @param list<non-empty-string> $proxyCIDRList
     * @return list<non-empty-string>
     * @throws InvalidProxyAddressException
     */
    private static function normalize_proxies_list(array $proxy_cidr_list): array
    {
        $found_wildcard = false;
        foreach ($proxy_cidr_list as $index => $cidr) {
            if ($cidr === '*') {
                unset($proxy_cidr_list[$index]);
                $found_wildcard = true;
                continue;
            }
            if (!self::validate_proxy_cidr($cidr)) {
                throw Invalid_Proxy_Address_Exception::for_address($cidr);
            }
        }
        if ($found_wildcard) {
            $proxy_cidr_list[] = '0.0.0.0/0';
            $proxy_cidr_list[] = '::/0';
        }
        return array_values($proxy_cidr_list);
    }
    private static function validate_proxy_cidr(mixed $cidr): bool
    {
        if (!is_string($cidr) || '' === $cidr) {
            return false;
        }
        $address = $cidr;
        $mask = null;
        if (str_contains($cidr, '/')) {
            $parts = explode('/', $cidr, 2);
            assert(count($parts) >= 2);
            [$address, $mask] = $parts;
            $mask = (int) $mask;
        }
        if (str_contains($address, ':')) {
            // is IPV6
            return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && ($mask === null || $mask <= 128 && $mask >= 0);
        }
        // is IPV4
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && ($mask === null || $mask <= 32 && $mask >= 0);
    }
}