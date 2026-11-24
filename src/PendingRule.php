<?php

declare(strict_types=1);

namespace Nwidart\Larch;

use Closure;
use Nwidart\Larch\Exceptions\LarchException;
use Nwidart\Larch\Graph\EdgeKind;
use Pest\Arch\Contracts\ArchExpectation;
use Pest\Expectation;

/**
 * A rule that runs once its kinds of link are known: when the chain calls
 * another method, or when the rule is let go at the end of the statement.
 * This is how via() can come after the rule, as Pest's own ignoring() does.
 *
 * @internal
 *
 * @mixin Expectation<mixed>
 */
final class PendingRule implements ArchExpectation
{
    /** @var list<EdgeKind> */
    private array $kinds = [EdgeKind::Import];

    private bool $checked = false;

    /**
     * @param  Expectation<mixed>  $expectation
     * @param  Closure(list<EdgeKind>): void  $check
     */
    public function __construct(
        private readonly Expectation $expectation,
        private readonly Closure $check,
    ) {}

    /**
     * Check these kinds of link instead of imports only.
     */
    public function via(Edge ...$edges): self
    {
        if ($this->checked) {
            throw new LarchException('Call via() right after the rule, before and().');
        }

        $kinds = [];

        foreach ($edges as $edge) {
            foreach ($edge->kinds as $kind) {
                $kinds[$kind->value] = $kind;
            }
        }

        if ($kinds === []) {
            $this->checked = true;

            throw new LarchException('via() needs at least one kind of link, such as Edge::all().');
        }

        $this->kinds = array_values($kinds);

        return $this;
    }

    public function check(): void
    {
        if ($this->checked) {
            return;
        }

        $this->checked = true;
        ($this->check)($this->kinds);
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $this->check();

        return $this->expectation->{$name}(...$arguments);
    }

    public function __get(string $name): mixed
    {
        $this->check();

        return $this->expectation->{$name};
    }

    public function __destruct()
    {
        $this->check();
    }

    /**
     * Pest passes its global ignore list here. Larch rules take no ignore
     * list, so it is not used.
     *
     * {@inheritDoc}
     */
    public function ignoring(array|string $targetsOrDependencies): self
    {
        return $this;
    }

    public function ignoringGlobalFunctions(): self
    {
        return $this;
    }

    public function mergeExcludeCallbacks(array $callbacks): void {}

    public function excludeCallbacks(): array
    {
        return [];
    }
}
