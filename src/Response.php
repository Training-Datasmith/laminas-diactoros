<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use Override;
use Psr\Http\Message\Response_Interface;
use Psr\Http\Message\Stream_Interface;
use function sprintf;
/**
 * HTTP response encapsulation.
 *
 * Responses are considered immutable; all methods that might change state are
 * implemented such that they retain the internal state of the current
 * message and return a new instance that contains the changed state.
 */
class Response implements Response_Interface
{
    use Message_Trait;
    public const MIN_STATUS_CODE_VALUE = 100;
    public const MAX_STATUS_CODE_VALUE = 599;
    /**
     * Map of standard HTTP status code/reason phrases
     *
     * @psalm-var array<positive-int, non-empty-string>
     */
    private array $phrases = [
        // INFORMATIONAL CODES
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        // phpcs:ignore Generic.Files.LineLength.TooLong
        104 => 'Upload Resumption Supported (TEMPORARY - registered 2024-11-13, extension registered 2025-09-15, expires 2026-11-13)',
        // SUCCESS CODES
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        // REDIRECTION CODES
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        // Deprecated to 306 => '(Unused)'
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        // CLIENT ERROR
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Content Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Content',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        444 => 'Connection Closed Without Response',
        451 => 'Unavailable For Legal Reasons',
        // SERVER ERROR
        499 => 'Client Closed Request',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended (OBSOLETED)',
        511 => 'Network Authentication Required',
        599 => 'Network Connect Timeout Error',
    ];
    private string $reason_phrase;
    private int $status_code;
    /**
     * @param string|resource|StreamInterface $body Stream identifier and/or actual stream resource
     * @param int $status Status code for the response, if any.
     * @param array<non-empty-string, string|string[]> $headers Headers for the response, if any.
     * @throws Exception\InvalidArgumentException On any invalid element.
     */
    public function __construct($body = 'php://memory', int $status = 200, array $headers = [])
    {
        $this->set_status_code($status);
        $this->stream = $this->get_stream($body, 'wb+');
        $this->set_headers($headers);
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_status_code(): int
    {
        return $this->status_code;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_reason_phrase(): string
    {
        return $this->reason_phrase;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function with_status(int $code, string $reason_phrase = ''): Response
    {
        $new = clone $this;
        $new->set_status_code($code, $reason_phrase);
        return $new;
    }
    /**
     * Set a valid status code.
     *
     * @throws Exception\InvalidArgumentException On an invalid status code.
     */
    private function set_status_code(int $code, string $reason_phrase = ''): void
    {
        if ($code < static::MIN_STATUS_CODE_VALUE || $code > static::MAX_STATUS_CODE_VALUE) {
            throw new Exception\InvalidArgumentException(sprintf('Invalid status code "%s"; must be an integer between %d and %d, inclusive', $code, self::MIN_STATUS_CODE_VALUE, self::MAX_STATUS_CODE_VALUE));
        }
        if ($reason_phrase === '' && isset($this->phrases[$code])) {
            $reason_phrase = $this->phrases[$code];
        }
        $this->reason_phrase = $reason_phrase;
        $this->status_code = $code;
    }
}