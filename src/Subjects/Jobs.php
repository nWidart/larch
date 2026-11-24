<?php

declare(strict_types=1);

namespace Nwidart\Larch\Subjects;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Nwidart\Larch\Larch;
use ReflectionClass;

/**
 * The jobs the code declares: classes that use the Bus Dispatchable trait.
 */
final readonly class Jobs
{
    public function __construct(private bool $queued = false) {}

    /**
     * Only jobs that implement ShouldQueue.
     */
    public function queued(): self
    {
        return new self(true);
    }

    /**
     * @return list<class-string>
     */
    public function resolve(Larch $larch): array
    {
        $jobs = [];

        foreach (array_keys($larch->graph()->classes) as $class) {
            if (! class_exists($class) || (new ReflectionClass($class))->isAbstract()
                || ! in_array(Dispatchable::class, class_uses_recursive($class), true)) {
                continue;
            }

            if ($this->queued && ! is_subclass_of($class, ShouldQueue::class)) {
                continue;
            }

            $jobs[] = $class;
        }

        return $jobs;
    }

    public function describe(): string
    {
        return $this->queued ? 'Queued jobs' : 'Jobs';
    }
}
