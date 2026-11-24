<?php

declare(strict_types=1);

namespace Nwidart\Larch\Subjects;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Str;
use ReflectionProperty;

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
     * Each listener with the event it listens to. A listener registered as
     * an object is called at once, even when it implements ShouldQueue.
     * A wildcard registration counts when the patterns match it, or match
     * a scanned class it matches.
     *
     * @param  array<string, string>  $classes  the scanned classes, class => file
     * @return list<array{listener: class-string, event: string, object: bool}>
     */
    public function resolve(Container $app, array $classes = []): array
    {
        $dispatcher = $app->make('events');

        if (! $dispatcher instanceof Dispatcher) {
            return [];
        }

        $found = [];
        $wildcards = (new ReflectionProperty($dispatcher, 'wildcards'))->getValue($dispatcher);

        foreach ([$dispatcher->getRawListeners(), is_array($wildcards) ? $wildcards : []] as $index => $registrations) {
            foreach ($registrations as $event => $listeners) {
                $event = (string) $event;

                if (! is_array($listeners) || ! ($index === 0 ? Str::is($this->events, $event) : $this->selects($event, $classes))) {
                    continue;
                }

                foreach ($listeners as $listener) {
                    [$class, $object] = match (true) {
                        is_string($listener) => [explode('@', $listener)[0], false],
                        is_array($listener) && is_string($listener[0] ?? null) => [$listener[0], false],
                        is_array($listener) && is_object($listener[0] ?? null) => [$listener[0]::class, true],
                        default => [null, false],
                    };

                    if ($class !== null && class_exists($class)) {
                        $found[$class.'|'.$event] = ['listener' => $class, 'event' => $event, 'object' => $object];
                    }
                }
            }
        }

        ksort($found);

        return array_values($found);
    }

    /**
     * @param  array<string, string>  $classes
     */
    private function selects(string $pattern, array $classes): bool
    {
        if (Str::is($this->events, $pattern)) {
            return true;
        }

        foreach (array_keys($classes) as $class) {
            if (Str::is($pattern, $class) && Str::is($this->events, $class)) {
                return true;
            }
        }

        return false;
    }

    public function describe(): string
    {
        return 'Listeners of '.implode(', ', $this->events);
    }
}
