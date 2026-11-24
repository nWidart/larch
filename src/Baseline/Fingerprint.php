<?php

declare(strict_types=1);

namespace Nwidart\Larch\Baseline;

/**
 * One violation as the baseline knows it: from class, to class, kind.
 * No file and no line, so moving code does not make a violation new.
 */
final readonly class Fingerprint
{
    public function __construct(
        public string $from,
        public string $to,
        public string $kind,
    ) {}

    public function key(): string
    {
        return $this->from.' > '.$this->to.' ('.$this->kind.')';
    }

    /**
     * @return array{from: string, to: string, kind: string}
     */
    public function toArray(): array
    {
        return ['from' => $this->from, 'to' => $this->to, 'kind' => $this->kind];
    }
}
