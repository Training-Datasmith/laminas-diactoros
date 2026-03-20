<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use function is_callable;
/**
 * Marshal the $_SERVER array
 *
 * Pre-processes and returns the $_SERVER superglobal. In particularly, it
 * attempts to detect the Authorization header, which is often not aggregated
 * correctly under various SAPI/httpd combinations.
 *
 * @param null|callable $apacheRequestHeaderCallback Callback that can be used to
 *     retrieve Apache request headers. This defaults to
 *     `apache_request_headers` under the Apache mod_php.
 * @return array Either $server verbatim, or with an added HTTP_AUTHORIZATION header.
 */
function normalize_server(array $server, ?callable $apache_request_header_callback = null): array
{
    if (null === $apache_request_header_callback && is_callable('apache_request_headers')) {
        $apache_request_header_callback = 'apache_request_headers';
    }
    // If the HTTP_AUTHORIZATION value is already set, or the callback is not
    // callable, we return verbatim
    if (isset($server['HTTP_AUTHORIZATION']) || !is_callable($apache_request_header_callback)) {
        return $server;
    }
    $apache_request_headers = $apache_request_header_callback();
    if (isset($apache_request_headers['Authorization'])) {
        $server['HTTP_AUTHORIZATION'] = $apache_request_headers['Authorization'];
        return $server;
    }
    if (isset($apache_request_headers['authorization'])) {
        $server['HTTP_AUTHORIZATION'] = $apache_request_headers['authorization'];
        return $server;
    }
    return $server;
}