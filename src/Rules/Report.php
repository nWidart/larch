<?php

declare(strict_types=1);

namespace Nwidart\Larch\Rules;

use Nwidart\Larch\Modules\ModuleLink;

/**
 * Writes the message of a failed rule.
 */
final class Report
{
    /**
     * How many links of one hop a cycle lists before it counts the rest.
     */
    private const int LINKS_PER_HOP = 5;

    /**
     * @param  list<Violation>  $violations
     */
    public static function violations(string $headline, array $violations): string
    {
        $count = count($violations);
        $lines = [$headline.' '.$count.' '.($count === 1 ? 'link breaks' : 'links break').' it:', ''];

        foreach ($violations as $violation) {
            $lines[] = '  '.self::place($violation->link);
            $lines[] = '    '.$violation->link->fromClass.' uses '.$violation->link->toClass;
            $lines[] = '    '.$violation->reason;
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    /**
     * @param  list<list<string>>  $cycles  the modules of each cycle
     * @param  list<ModuleLink>  $links
     */
    public static function cycles(array $cycles, array $links): string
    {
        $count = count($cycles);
        $lines = ['Modules use each other in '.$count.' '.($count === 1 ? 'cycle' : 'cycles').':', ''];

        foreach ($cycles as $modules) {
            $lines[] = '  '.implode(', ', $modules);

            foreach ($modules as $from) {
                foreach ($modules as $to) {
                    $hop = array_values(array_filter(
                        $links,
                        fn (ModuleLink $link): bool => $link->fromModule === $from && $link->toModule === $to,
                    ));

                    if ($hop === []) {
                        continue;
                    }

                    $places = count($hop);
                    $lines[] = '    '.$from.' uses '.$to.' in '.$places.' '.($places === 1 ? 'place' : 'places').':';

                    foreach (array_slice($hop, 0, self::LINKS_PER_HOP) as $link) {
                        $lines[] = '      '.self::place($link).'  '.$link->fromClass.' uses '.$link->toClass;
                    }

                    if ($places > self::LINKS_PER_HOP) {
                        $lines[] = '      and '.($places - self::LINKS_PER_HOP).' more';
                    }
                }
            }

            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    private static function place(ModuleLink $link): string
    {
        $first = $link->edges[0];
        $others = array_values(array_unique(array_map(
            fn ($edge): int => $edge->line,
            array_filter($link->edges, fn ($edge): bool => $edge->file === $first->file && $edge->line !== $first->line),
        )));

        $place = $first->file.':'.$first->line;

        if ($others !== []) {
            $place .= ' (also '.(count($others) === 1 ? 'line ' : 'lines ').implode(', ', $others).')';
        }

        return $place;
    }
}
