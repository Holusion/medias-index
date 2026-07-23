<?php

declare(strict_types=1);

namespace MediasIndex\Http;

/**
 * Maps a method and path to a handler.
 *
 * Patterns use `{name}` placeholders, which match a single path segment:
 *
 *     $router->get('/c/{client}', $handler);
 *
 * A placeholder never matches a slash, so a slug can neither swallow the rest of
 * the path nor smuggle a traversal through it.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => self::compile($pattern),
            'handler' => $handler,
        ];
    }

    /**
     * @return Response|null null when nothing matched, so the caller decides what
     *                       a 404 looks like
     */
    public function dispatch(Request $request): ?Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }

            $parameters = array_filter(
                $matches,
                static fn (int|string $key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY,
            );

            return ($route['handler'])($request, array_map(urldecode(...), $parameters));
        }

        return null;
    }

    /**
     * Literal parts are quoted, placeholders become named groups.
     *
     * Split first, quote after: quoting the whole pattern up front would escape
     * the braces and the placeholders would never be recognised.
     */
    private static function compile(string $pattern): string
    {
        $parts = preg_split(
            '/(\{[a-z_][a-z0-9_]*\})/i',
            $pattern,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        ) ?: [];

        $regex = '';

        foreach ($parts as $part) {
            $regex .= preg_match('/^\{([a-z_][a-z0-9_]*)\}$/i', $part, $m) === 1
                ? '(?P<' . $m[1] . '>[^/]+)'
                : preg_quote($part, '#');
        }

        return '#^' . $regex . '$#';
    }
}
