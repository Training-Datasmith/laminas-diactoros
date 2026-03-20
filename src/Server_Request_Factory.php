<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use function array_key_exists;
use function is_callable;
use Laminas\Diactoros\Server_Request_Filter\Filter_Server_Request_Interface;
use Laminas\Diactoros\Server_Request_Filter\Filter_Using_X_Forwarded_Headers;
use Override;
use Psr\Http\Message\Server_Request_Factory_Interface;
use Psr\Http\Message\Server_Request_Interface;
/**
 * Class for marshaling a request object from the current PHP environment.
 */
class Server_Request_Factory implements Server_Request_Factory_Interface
{
    /**
     * Function to use to get apache request headers; present only to simplify mocking.
     *
     * @var callable|string
     */
    private static string $apache_request_headers = 'apache_request_headers';
    /**
     * Create a request from the supplied superglobal values.
     *
     * If any argument is not supplied, the corresponding superglobal value will
     * be used.
     *
     * The ServerRequest created is then passed to the fromServer() method in
     * order to marshal the request URI and headers.
     *
     * @see fromServer()
     *
     * @param null|array $server $_SERVER superglobal
     * @param null|array $query $_GET superglobal
     * @param null|array $body $_POST superglobal
     * @param null|array $cookies $_COOKIE superglobal
     * @param null|array $files $_FILES superglobal
     * @param null|FilterServerRequestInterface $requestFilter If present, the
     *     generated request will be passed to this instance and the result
     *     returned by this method. When not present, a default instance of
     *     FilterUsingXForwardedHeaders is created, using the `trustReservedSubnets()`
     *     constructor.
     */
    public static function from_globals(?array $server = null, ?array $query = null, ?array $body = null, ?array $cookies = null, ?array $files = null, ?Filter_Server_Request_Interface $request_filter = null): Server_Request_Interface
    {
        $request_filter ??= Filter_Using_X_Forwarded_Headers::trust_reserved_subnets();
        $server = normalize_server($server ?? $_SERVER, is_callable(self::$apache_request_headers) ? self::$apache_request_headers : null);
        $files = normalize_uploaded_files($files ?? $_FILES);
        $headers = marshal_headers_from_sapi($server);
        if (null === $cookies && array_key_exists('cookie', $headers)) {
            $cookies = parse_cookie_header($headers['cookie']);
        }
        return $request_filter(new Server_Request($server, $files, Uri_Factory::create_from_sapi($server, $headers), marshal_method_from_sapi($server), 'php://input', $headers, $cookies ?? $_COOKIE, $query ?? $_GET, $body ?? $_POST, marshal_protocol_version_from_sapi($server)));
    }
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function create_server_request(string $method, $uri, array $server_params = []): Server_Request_Interface
    {
        $uploaded_files = [];
        return new Server_Request($server_params, $uploaded_files, $uri, $method, 'php://temp');
    }
}