<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Response;

use Laminas\Diactoros\Exception;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\Response_Interface;
use function sprintf;
use Throwable;
/**
 * Serialize or deserialize response messages to/from arrays.
 *
 * This class provides functionality for serializing a ResponseInterface instance
 * to an array, as well as the reverse operation of creating a Response instance
 * from an array representing a message.
 */
final class Array_Serializer
{
    /**
     * Serialize a response message to an array.
     *
     * @return array{
     *     status_code: int,
     *     reason_phrase: string,
     *     protocol_version: string,
     *     headers: array<array<string>>,
     *     body: string
     * }
     */
    public static function to_array(Response_Interface $response): array
    {
        return ['status_code' => $response->get_status_code(), 'reason_phrase' => $response->get_reason_phrase(), 'protocol_version' => $response->get_protocol_version(), 'headers' => $response->get_headers(), 'body' => (string) $response->get_body()];
    }
    /**
     * Deserialize a response array to a response instance.
     *
     * @throws Exception\DeserializationException When cannot deserialize response.
     */
    public static function from_array(array $serialized_response): Response
    {
        try {
            $body = new Stream('php://memory', 'wb+');
            $body->write(self::get_value_from_key($serialized_response, 'body'));
            $status_code = self::get_value_from_key($serialized_response, 'status_code');
            $headers = self::get_value_from_key($serialized_response, 'headers');
            $protocol_version = self::get_value_from_key($serialized_response, 'protocol_version');
            $reason_phrase = self::get_value_from_key($serialized_response, 'reason_phrase');
            return (new Response($body, $status_code, $headers))->with_protocol_version($protocol_version)->with_status($status_code, $reason_phrase);
        } catch (Throwable $exception) {
            throw Exception\Deserialization_Exception::for_response_from_array($exception);
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
            $message = sprintf('Missing "%s" key in serialized response', $key);
        }
        throw new Exception\Deserialization_Exception($message);
    }
}