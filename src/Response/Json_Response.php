<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Response;

use function is_object;
use function is_resource;
use function json_encode;
use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use Json_Exception;
use Laminas\Diactoros\Exception;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;
use function sprintf;
/**
 * JSON response.
 *
 * Allows creating a response by passing data to the constructor; by default,
 * serializes the data to JSON, sets a status code of 200 and sets the
 * Content-Type header to application/json.
 */
class Json_Response extends Response
{
    use Inject_Content_Type_Trait;
    /**
     * Default flags for json_encode
     *
     * @const int
     */
    public const DEFAULT_JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
    private mixed $payload;
    /**
     * Create a JSON response with the given data.
     *
     * Default JSON encoding is performed with the following options, which
     * produces RFC4627-compliant JSON, capable of embedding into HTML.
     *
     * - JSON_HEX_TAG
     * - JSON_HEX_APOS
     * - JSON_HEX_AMP
     * - JSON_HEX_QUOT
     * - JSON_UNESCAPED_SLASHES
     *
     * @param mixed $data Data to convert to JSON.
     * @param int $status Integer status code for the response; 200 by default.
     * @param array<non-empty-string, string|string[]> $headers Array of headers to use at initialization.
     * @param int $encodingOptions JSON encoding options to use.
     * @throws Exception\InvalidArgumentException If unable to encode the $data to JSON.
     */
    public function __construct($data, int $status = 200, array $headers = [], private int $encoding_options = self::DEFAULT_JSON_FLAGS)
    {
        $this->set_payload($data);
        $json = $this->json_encode($data, $this->encoding_options);
        $body = $this->create_body_from_json($json);
        $headers = $this->inject_content_type('application/json', $headers);
        parent::__construct($body, $status, $headers);
    }
    /**
     * @return mixed
     */
    public function get_payload()
    {
        return $this->payload;
    }
    public function with_payload(mixed $data): Json_Response
    {
        $new = clone $this;
        $new->set_payload($data);
        return $this->update_body_for($new);
    }
    public function get_encoding_options(): int
    {
        return $this->encoding_options;
    }
    public function with_encoding_options(int $encoding_options): Json_Response
    {
        $new = clone $this;
        $new->encoding_options = $encoding_options;
        return $this->update_body_for($new);
    }
    private function create_body_from_json(string $json): Stream
    {
        $body = new Stream('php://temp', 'wb+');
        $body->write($json);
        $body->rewind();
        return $body;
    }
    /**
     * Encode the provided data to JSON.
     *
     * @throws Exception\InvalidArgumentException If unable to encode the $data to JSON.
     */
    private function json_encode(mixed $data, int $encoding_options): string
    {
        if (is_resource($data)) {
            throw new Exception\InvalidArgumentException('Cannot JSON encode resources');
        }
        try {
            return json_encode($data, $encoding_options | JSON_THROW_ON_ERROR);
        } catch (Json_Exception $e) {
            throw new Exception\InvalidArgumentException(sprintf('Unable to encode data to JSON in %s: %s', self::class, $e->get_message()), 0, $e);
        }
    }
    private function set_payload(mixed $data): void
    {
        if (is_object($data)) {
            $data = clone $data;
        }
        $this->payload = $data;
    }
    /**
     * Update the response body for the given instance.
     *
     * @param self $toUpdate Instance to update.
     * @return JsonResponse Returns a new instance with an updated body.
     */
    private function update_body_for(Json_Response $to_update): Json_Response
    {
        $json = $this->json_encode($to_update->payload, $to_update->encoding_options);
        $body = $this->create_body_from_json($json);
        return $to_update->with_body($body);
    }
}