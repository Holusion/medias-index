<?php

declare(strict_types=1);

namespace MediasIndex\Http;

/**
 * An incoming request, reduced to what this application actually uses.
 *
 * Built from globals only at the very edge (fromGlobals), so everything below —
 * routing, controllers, tests — works with an explicit object.
 */
final readonly class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, string> $post
     */
    private function __construct(
        public string $method,
        public string $path,
        private array $query,
        private array $post,
        /** Origin header, when the browser sent one. */
        private ?string $origin = null,
        /**
         * Set when Apache is serving this request as an ErrorDocument, to the
         * status that caused it. Null on an ordinary request.
         */
        public ?int $errorStatus = null,
    ) {
    }

    public static function fromGlobals(): self
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;

        return self::create(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'),
            array_map(strval(...), $_GET),
            array_map(strval(...), $_POST),
            errorStatus: self::redirectStatus(),
            origin: is_string($origin) && $origin !== '' ? $origin : null,
        );
    }

    /**
     * Named arguments beyond the first four, so inserting one cannot silently
     * shift the meaning of an existing call.
     *
     * @param array<string, string> $query
     * @param array<string, string> $post
     */
    public static function create(
        string $method,
        string $path,
        array $query = [],
        array $post = [],
        ?int $errorStatus = null,
        ?string $origin = null,
    ): self {
        return new self(
            strtoupper($method),
            self::normalisePath($path),
            $query,
            $post,
            origin: $origin,
            errorStatus: $errorStatus,
        );
    }

    /** Where a browser says the request came from, if it said. */
    public function origin(): ?string
    {
        return $this->origin;
    }

    /**
     * The status Apache is reporting through an ErrorDocument, if any.
     *
     * REQUEST_URI still holds the URL that failed rather than the ErrorDocument
     * path, so the status is the only thing that says why we are here. It also
     * carries 200 on every ordinary request under PHP-FPM, hence the floor.
     */
    private static function redirectStatus(): ?int
    {
        $status = (int) ($_SERVER['REDIRECT_STATUS'] ?? 0);

        return $status >= 400 ? $status : null;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function queryInt(string $key, int $default = 0): int
    {
        $value = $this->query($key);

        return $value !== null && ctype_digit($value) ? (int) $value : $default;
    }

    public function post(string $key, ?string $default = null): ?string
    {
        $value = $this->post[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    /** Trailing slashes are dropped so "/c/acme" and "/c/acme/" are one route. */
    private static function normalisePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path;
    }
}
