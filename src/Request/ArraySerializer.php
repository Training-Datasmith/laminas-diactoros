<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Request;

use Laminas\Diactoros\Exception;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\Request_Interface;
use function sprintf;
use Throwable;
/**
 * Serialize or deserialize request messages to/from arrays.
 *
 * This class provides functionality for serializing a RequestInterface instance
 * to an array, as well as the reverse operation of creating a Request instance
 * from an array representing a message.
 */
final class Array_Serializer
{
    /**
     * Serialize a request message to an array.
     *
     * @return array{
     *     method: string,
     *     request_target: string,
     *     uri: string,
     *     protocol_version: string,
     *     headers: array<array<string>>,
     *     body: string
     * }
     */
    public static function to_array(Request_Interface $request): array
    {
        return ['method' => $request->get_method(), 'request_target' => $request->get_request_target(), 'uri' => (string) $request->get_uri(), 'protocol_version' => $request->get_protocol_version(), 'headers' => $request->get_headers(), 'body' => (string) $request->get_body()];
    }
    /**
     * Deserialize a request array to a request instance.
     *
     * @throws Exception\DeserializationException When the response cannot be deserialized.
     */
    public static function from_array(array $serialized_request): Request
    {
        try {
            $uri = self::get_value_from_key($serialized_request, 'uri');
            $method = self::get_value_from_key($serialized_request, 'method');
            $body = new Stream('php://memory', 'wb+');
            $body->write(self::get_value_from_key($serialized_request, 'body'));
            $headers = self::get_value_from_key($serialized_request, 'headers');
            $request_target = self::get_value_from_key($serialized_request, 'request_target');
            $protocol_version = self::get_value_from_key($serialized_request, 'protocol_version');
            return (new Request($uri, $method, $body, $headers))->with_request_target($request_target)->with_protocol_version($protocol_version);
        } catch (Throwable $exception) {
            throw Exception\Deserialization_Exception::for_request_from_array($exception);
        }
    }
    /**
     * @throws Exception\DeserializationException
     */
    private static function get_value_from_key(array $data, string $key, ?string $message = null): mixed
    {
        if (isset($data[$key])) {
            return $data[$key];
        }
        if ($message === null) {
            $message = sprintf('Missing "%s" key in serialized request', $key);
        }
        throw new Exception\Deserialization_Exception($message);
    }
}