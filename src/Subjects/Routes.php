<?php

declare(strict_types=1);

namespace Nwidart\Larch\Subjects;

use Illuminate\Container\Container;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * The routes of the booted app, narrowed by prefix, name and exceptions.
 */
final readonly class Routes
{
    /**
     * @param  list<string>  $prefixes
     * @param  list<string>  $names
     * @param  list<string>  $except
     */
    public function __construct(
        private array $prefixes = [],
        private array $names = [],
        private array $except = [],
    ) {}

    /**
     * Only routes whose URI starts with one of these segments: 'api'.
     */
    public function prefixed(string ...$prefixes): self
    {
        $prefixes = array_values(array_map(fn (string $prefix): string => trim($prefix, '/'), $prefixes));

        return new self([...$this->prefixes, ...$prefixes], $this->names, $this->except);
    }

    /**
     * Only routes whose name matches one of these patterns: 'admin.*'.
     */
    public function named(string ...$patterns): self
    {
        return new self($this->prefixes, [...$this->names, ...array_values($patterns)], $this->except);
    }

    /**
     * Not the routes whose URI or name matches one of these patterns:
     * 'api/webhooks/*', 'api/health', 'admin.login'.
     */
    public function except(string ...$patterns): self
    {
        $patterns = array_values(array_map(fn (string $pattern): string => ltrim($pattern, '/'), $patterns));

        return new self($this->prefixes, $this->names, [...$this->except, ...$patterns]);
    }

    /**
     * @return list<Route>
     */
    public function resolve(Container $app): array
    {
        $router = $app->make('router');

        if (! $router instanceof Router) {
            return [];
        }

        $routes = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if ($this->matches($route)) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    public function describe(): string
    {
        $text = 'Routes';

        if ($this->prefixes !== []) {
            $text .= ' under '.implode(', ', $this->prefixes);
        }

        if ($this->names !== []) {
            $text .= ' named '.implode(', ', $this->names);
        }

        if ($this->except !== []) {
            $text .= ', except '.implode(', ', $this->except).',';
        }

        return $text;
    }

    private function matches(Route $route): bool
    {
        $uri = trim($route->uri(), '/');
        $name = $route->getName();

        if ($this->prefixes !== [] && array_filter($this->prefixes, fn (string $prefix): bool => $uri === $prefix || str_starts_with($uri, $prefix.'/')) === []) {
            return false;
        }

        if ($this->names !== [] && ($name === null || ! Str::is($this->names, $name))) {
            return false;
        }

        return ! Str::is($this->except, $uri) && ($name === null || ! Str::is($this->except, $name));
    }
}
