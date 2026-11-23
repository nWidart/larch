<?php

declare(strict_types=1);

namespace Nwidart\Larch\Rules;

/**
 * Tarjan's algorithm: groups of nodes where each reaches every other.
 * A group of two or more is a cycle.
 */
final class StronglyConnected
{
    /**
     * @param  array<string, array<string, true>>  $adjacency
     * @return list<list<string>> each group sorted by name, groups in order of their first name
     */
    public static function components(array $adjacency): array
    {
        $index = 0;
        $indexes = [];
        $lowLinks = [];
        $stack = [];
        $onStack = [];
        $components = [];

        $visit = function (string $node) use (&$visit, &$index, &$indexes, &$lowLinks, &$stack, &$onStack, &$components, $adjacency): void {
            $indexes[$node] = $lowLinks[$node] = $index++;
            $stack[] = $node;
            $onStack[$node] = true;

            foreach (array_keys($adjacency[$node] ?? []) as $next) {
                if (! isset($indexes[$next])) {
                    $visit($next);
                    $lowLinks[$node] = min($lowLinks[$node], $lowLinks[$next]);
                } elseif (isset($onStack[$next])) {
                    $lowLinks[$node] = min($lowLinks[$node], $indexes[$next]);
                }
            }

            if ($lowLinks[$node] === $indexes[$node]) {
                $component = [];

                do {
                    $member = array_pop($stack);
                    unset($onStack[$member]);
                    $component[] = $member;
                } while ($member !== $node);

                sort($component);
                $components[] = $component;
            }
        };

        $nodes = array_keys($adjacency);
        sort($nodes);

        foreach ($nodes as $node) {
            if (! isset($indexes[$node])) {
                $visit((string) $node);
            }
        }

        usort($components, fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        return $components;
    }
}
