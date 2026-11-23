<?php

declare(strict_types=1);

namespace Nwidart\Larch\Subjects;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Str;

/**
 * The listeners of the events whose class matches the patterns, as the
 * booted app registers them. Closures are skipped: they cannot be queued.
 */
final readonly class Listeners
{
    /**
     * @param  list<string>  $events  patterns: 'Modules\Billing\Events\*'
     */
    public function __construct(private array $events) {}

    /**
     * @return list<array{listener: class-string, event: string}>
     */
    public function resolve(Container $app): array
    {
        $dispatcher = $app->make('events');

        if (! $dispatcher instanceof Dispatcher) {
            return [];
        }

        $found = [];

        foreach ($dispatcher->getRawListeners() as $event => $listeners) {
            if (! Str::is($this->events, $event) || ! is_array($listeners)) {
                continue;
            }

            foreach ($listeners as $listener) {
                $class = match (true) {
                    is_string($listener) => explode('@', $listener)[0],
                    is_array($listener) && is_string($listener[0] ?? null) => $listener[0],
                    is_array($listener) && is_object($listener[0] ?? null) => $listener[0]::class,
                    default => null,
                };

                if ($class !== null && class_exists($class)) {
                    $found[$class.'|'.$event] = ['listener' => $class, 'event' => $event];
                }
            }
        }

        ksort($found);

        return array_values($found);
    }

    public function describe(): string
    {
        return 'Listeners of '.implode(', ', $this->events);
    }
}
