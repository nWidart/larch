<?php

declare(strict_types=1);

namespace Nwidart\Larch\Subjects;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Nwidart\Larch\Larch;
use ReflectionClass;

/**
 * The Eloquent models the code declares.
 */
final readonly class Models
{
    /**
     * @param  list<string>  $except
     */
    public function __construct(
        private array $except = [],
        private bool $morphs = false,
    ) {}

    /**
     * Not these models, models that extend them, or models whose class
     * matches the pattern: except(Pivot::class, 'App\Legacy\*').
     */
    public function except(string ...$classes): self
    {
        return new self([...$this->except, ...array_values(array_map(fn (string $class): string => ltrim($class, '\\'), $classes))], $this->morphs);
    }

    /**
     * Only models a morph relation stores by class: models that declare
     * morphOne, morphMany or morphToMany, and the models of morphedByMany.
     */
    public function usedInMorphs(): self
    {
        return new self($this->except, true);
    }

    /**
     * @return list<class-string<Model>>
     */
    public function resolve(Larch $larch): array
    {
        $graph = $larch->graph();
        $candidates = $this->morphs ? $graph->morphs : array_keys($graph->classes);
        $models = [];

        foreach ($candidates as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, Model::class) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            if ($this->isExcepted($class)) {
                continue;
            }

            $models[] = $class;
        }

        return $models;
    }

    public function describe(): string
    {
        $text = $this->morphs ? 'Models used in morph relations' : 'Models';

        return $this->except === [] ? $text : $text.', except '.implode(', ', $this->except).',';
    }

    private function isExcepted(string $class): bool
    {
        foreach ($this->except as $except) {
            if (is_a($class, $except, true) || Str::is($except, $class)) {
                return true;
            }
        }

        return false;
    }
}
