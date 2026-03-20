<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use function array_keys;
use function assert;
use function explode;
use function implode;
use function is_string;
use function ltrim;
use Override;
use function parse_url;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use Psr\Http\Message\Uri_Interface;
use function rawurlencode;
use Sensitive_Parameter;
use function sprintf;
use function str_contains;
use function str_split;
use function str_starts_with;
use Stringable;
use function strtolower;
use function substr;
/**
 * Implementation of Psr\Http\UriInterface.
 *
 * Provides a value object representing a URI for HTTP requests.
 *
 * Instances of this class  are considered immutable; all methods that
 * might change state are implemented such that they retain the internal
 * state of the current instance and return a new instance that contains the
 * changed state.
 *
 * @psalm-immutable
 */
class Uri implements Uri_Interface, Stringable
{
    /**
     * Sub-delimiters used in user info, query strings and fragments.
     *
     * @const string
     */
    public const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';
    /**
     * Unreserved characters used in user info, paths, query strings, and fragments.
     *
     * @const string
     */
    public const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~\pL';
    /**
     * Array indexed by valid scheme names to their corresponding ports.
     *
     * @var array<string, positive-int>
     */
    protected $allowed_schemes = ['http' => 80, 'https' => 443];
    private string $scheme = '';
    private string $user_info = '';
    private string $host = '';
    private ?int $port = null;
    private string $path = '';
    private string $query = '';
    private string $fragment = '';
    /**
     * generated uri string cache
     */
    private ?string $uri_string = null;
    public function __construct(string $uri = '')
    {
        if ('' === $uri) {
            return;
        }
        /** @psalm-suppress UnusedMethodCall Called method is not mutation free. Psalm has no impure annotation */
        $this->parse_uri($uri);
    }
    /**
     * Operations to perform on clone.
     *
     * Since cloning usually is for purposes of mutation, we reset the
     * $uriString property so it will be re-calculated.
     */
    public function __clone()
    {
        $this->uri_string = null;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function __toString(): string
    {
        if (null !== $this->uri_string) {
            return $this->uri_string;
        }
        /** @psalm-suppress ImpureMethodCall, InaccessibleProperty */
        $this->uri_string = static::create_uri_string(
            $this->scheme,
            $this->get_authority(),
            $this->path,
            // Absolute URIs should use a "/" for an empty path
            $this->query,
            $this->fragment
        );
        return $this->uri_string;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_scheme(): string
    {
        return $this->scheme;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_authority(): string
    {
        if ('' === $this->host) {
            return '';
        }
        $authority = $this->host;
        if ('' !== $this->user_info) {
            $authority = $this->user_info . '@' . $authority;
        }
        if ($this->is_non_standard_port($this->scheme, $this->host, $this->port)) {
            $authority .= ':' . $this->port;
        }
        return $authority;
    }
    /**
     * Retrieve the user-info part of the URI.
     *
     * This value is percent-encoded, per RFC 3986 Section 3.2.1.
     *
     * {@inheritdoc}
     */
    #[Override]
    public function get_user_info(): string
    {
        return $this->user_info;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_host(): string
    {
        return $this->host;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_port(): ?int
    {
        return $this->is_non_standard_port($this->scheme, $this->host, $this->port) ? $this->port : null;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_path(): string
    {
        if ('' === $this->path) {
            // No path
            return $this->path;
        }
        if ($this->path[0] !== '/') {
            // Relative path
            return $this->path;
        }
        // Ensure only one leading slash, to prevent XSS attempts.
        return '/' . ltrim($this->path, '/');
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_query(): string
    {
        return $this->query;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_fragment(): string
    {
        return $this->fragment;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function with_scheme(string $scheme): Uri_Interface
    {
        $scheme = $this->filter_scheme($scheme);
        if ($scheme === $this->scheme) {
            // Do nothing if no change was made.
            return $this;
        }
        $new = clone $this;
        $new->scheme = $scheme;
        return $new;
    }
    // The following rule is buggy for parameters attributes
    // phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHintSpacing.NoSpaceBetweenTypeHintAndParameter
    /**
     * Create and return a new instance containing the provided user credentials.
     *
     * The value will be percent-encoded in the new instance, but with measures
     * taken to prevent double-encoding.
     *
     * {@inheritdoc}
     */
    #[Override]
    public function with_user_info(
        string $user,
        #[Sensitive_Parameter]
        ?string $password = null
    ): Uri_Interface
    {
        $info = $this->filter_user_info_part($user);
        if (null !== $password) {
            $info .= ':' . $this->filter_user_info_part($password);
        }
        if ($info === $this->user_info) {
            // Do nothing if no change was made.
            return $this;
        }
        $new = clone $this;
        $new->user_info = $info;
        return $new;
    }
    // phpcs:enable SlevomatCodingStandard.TypeHints.ParameterTypeHintSpacing.NoSpaceBetweenTypeHintAndParameter
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function with_host(string $host): Uri_Interface
    {
        if ($host === $this->host) {
            // Do nothing if no change was made.
            return $this;
        }
        $new = clone $this;
        $new->host = strtolower($host);
        return $new;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function with_port(?int $port): Uri_Interface
    {
        if ($port === $this->port) {
            // Do nothing if no change was made.
            return $this;
        }
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new Exception\InvalidArgumentException(sprintf('Invalid port "%d" specified; must be a valid TCP/UDP port', $port));
        }
        $new = clone $this;
        $new->port = $port;
        return $new;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function with_path(string $path): Uri_Interface
    {
        if (str_contains($path, '?')) {
            throw new Exception\InvalidArgumentException('Invalid path provided; must not contain a query string');
        }
        if (str_contains($path, '#')) {
            throw new Exception\InvalidArgumentException('Invalid path provided; must not contain a URI fragment');
        }
        $path = $this->filter_path($path);
        if ($path === $this->path) {
            // Do nothing if no change was made.
            return $this;
        }
        $new = clone $this;
        $new->path = $path;
        return $new;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function with_query(string $query): Uri_Interface
    {
        if (str_contains($query, '#')) {
            throw new Exception\InvalidArgumentException('Query string must not include a URI fragment');
        }
        $query = $this->filter_query($query);
        if ($query === $this->query) {
            // Do nothing if no change was made.
            return $this;
        }
        $new = clone $this;
        $new->query = $query;
        return $new;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function with_fragment(string $fragment): Uri_Interface
    {
        $fragment = $this->filter_fragment($fragment);
        if ($fragment === $this->fragment) {
            // Do nothing if no change was made.
            return $this;
        }
        $new = clone $this;
        $new->fragment = $fragment;
        return $new;
    }
    /**
     * Parse a URI into its parts, and set the properties
     *
     * @psalm-suppress InaccessibleProperty Method is only called in {@see Uri::__construct} and thus immutability is
     *                                      still given.
     */
    private function parse_uri(string $uri): void
    {
        $parts = parse_url($uri);
        if (false === $parts) {
            throw new Exception\InvalidArgumentException('The source URI string appears to be malformed');
        }
        $this->scheme = isset($parts['scheme']) ? $this->filter_scheme($parts['scheme']) : '';
        $this->user_info = isset($parts['user']) ? $this->filter_user_info_part($parts['user']) : '';
        $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
        $this->port = $parts['port'] ?? null;
        $this->path = isset($parts['path']) ? $this->filter_path($parts['path']) : '';
        $this->query = isset($parts['query']) ? $this->filter_query($parts['query']) : '';
        $this->fragment = isset($parts['fragment']) ? $this->filter_fragment($parts['fragment']) : '';
        if (isset($parts['pass'])) {
            $this->user_info .= ':' . $parts['pass'];
        }
    }
    /**
     * Create a URI string from its various parts
     */
    private static function create_uri_string(string $scheme, string $authority, string $path, string $query, string $fragment): string
    {
        $uri = '';
        if ('' !== $scheme) {
            $uri .= sprintf('%s:', $scheme);
        }
        if ('' !== $authority) {
            $uri .= '//' . $authority;
        }
        if ('' !== $path && !str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        $uri .= $path;
        if ('' !== $query) {
            $uri .= sprintf('?%s', $query);
        }
        if ('' !== $fragment) {
            $uri .= sprintf('#%s', $fragment);
        }
        return $uri;
    }
    /**
     * Is a given port non-standard for the current scheme?
     *
     * @psalm-assert-if-true int $port
     */
    private function is_non_standard_port(string $scheme, string $host, ?int $port): bool
    {
        if ('' === $scheme) {
            return '' === $host || null !== $port;
        }
        if ('' === $host || null === $port) {
            return false;
        }
        return !isset($this->allowed_schemes[$scheme]) || $port !== $this->allowed_schemes[$scheme];
    }
    /**
     * Filters the scheme to ensure it is a valid scheme.
     *
     * @param string $scheme Scheme name.
     * @return string Filtered scheme.
     */
    private function filter_scheme(string $scheme): string
    {
        $scheme = strtolower($scheme);
        $scheme = preg_replace('#:(//)?$#', '', $scheme);
        assert(is_string($scheme));
        if ('' === $scheme) {
            return '';
        }
        if (!isset($this->allowed_schemes[$scheme])) {
            throw new Exception\InvalidArgumentException(sprintf('Unsupported scheme "%s"; must be any empty string or in the set (%s)', $scheme, implode(', ', array_keys($this->allowed_schemes))));
        }
        return $scheme;
    }
    /**
     * Filters a part of user info in a URI to ensure it is properly encoded.
     */
    private function filter_user_info_part(string $part): string
    {
        $part = $this->filter_invalid_utf8($part);
        /**
         * Note the addition of `%` to initial charset; this allows `|` portion
         * to match and thus prevent double-encoding.
         */
        $result = preg_replace_callback('/(?:[^%' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ']+|%(?![A-Fa-f0-9]{2}))/u', $this->url_encode_char(...), $part);
        assert($result !== null, 'Always true condition for psalm type safety');
        return $result;
    }
    /**
     * Filters the path of a URI to ensure it is properly encoded.
     */
    private function filter_path(string $path): string
    {
        $path = $this->filter_invalid_utf8($path);
        $result = preg_replace_callback('/(?:[^' . self::CHAR_UNRESERVED . ')(:@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/u', $this->url_encode_char(...), $path);
        assert($result !== null, 'Always true condition for psalm type safety');
        return $result;
    }
    /**
     * Encode invalid UTF-8 characters in given string. All other characters are unchanged.
     */
    private function filter_invalid_utf8(string $string): string
    {
        // check if given string contains only valid UTF-8 characters
        if (preg_match('//u', $string)) {
            return $string;
        }
        $letters = str_split($string);
        foreach ($letters as $i => $letter) {
            if (!preg_match('//u', $letter)) {
                $letters[$i] = $this->url_encode_char([$letter]);
            }
        }
        return implode('', $letters);
    }
    /**
     * Filter a query string to ensure it is propertly encoded.
     *
     * Ensures that the values in the query string are properly urlencoded.
     */
    private function filter_query(string $query): string
    {
        if ('' !== $query && str_starts_with($query, '?')) {
            $query = substr($query, 1);
        }
        $parts = explode('&', $query);
        foreach ($parts as $index => $part) {
            [$key, $value] = $this->split_query_value($part);
            if ($value === null) {
                $parts[$index] = $this->filter_query_or_fragment($key);
                continue;
            }
            $parts[$index] = sprintf('%s=%s', $this->filter_query_or_fragment($key), $this->filter_query_or_fragment($value));
        }
        return implode('&', $parts);
    }
    /**
     * Split a query value into a key/value tuple.
     *
     * @return array{0:string, 1:string|null} A value with exactly two elements, key and value
     */
    private function split_query_value(string $value): array
    {
        $data = explode('=', $value, 2);
        if (!isset($data[1])) {
            $data[1] = null;
        }
        return $data;
    }
    /**
     * Filter a fragment value to ensure it is properly encoded.
     */
    private function filter_fragment(string $fragment): string
    {
        if ('' !== $fragment && str_starts_with($fragment, '#')) {
            $fragment = '%23' . substr($fragment, 1);
        }
        return $this->filter_query_or_fragment($fragment);
    }
    /**
     * Filter a query string key or value, or a fragment.
     */
    private function filter_query_or_fragment(string $value): string
    {
        $value = $this->filter_invalid_utf8($value);
        $result = preg_replace_callback('/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/\?]+|%(?![A-Fa-f0-9]{2}))/u', $this->url_encode_char(...), $value);
        assert($result !== null, 'Always true condition for psalm type safety');
        return $result;
    }
    /**
     * URL encode a character returned by a regex.
     *
     * @param array<string> $matches
     * @psalm-pure
     */
    private function url_encode_char(array $matches): string
    {
        return rawurlencode($matches[0]);
    }
}