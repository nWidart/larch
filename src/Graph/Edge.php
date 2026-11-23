<?php

declare(strict_types=1);

namespace Nwidart\Larch\Graph;

/**
 * One place where a class reaches another class.
 */
final readonly class Edge
{
    public function __construct(
        public string $from,
        public string $to,
        public EdgeKind $kind,
        public string $file,
        public int $line,
    ) {}
}
