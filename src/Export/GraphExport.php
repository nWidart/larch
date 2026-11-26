<?php

declare(strict_types=1);

namespace Nwidart\Larch\Export;

use Nwidart\Larch\Edge;
use Nwidart\Larch\Graph\EdgeKind;
use Nwidart\Larch\Larch;
use Nwidart\Larch\Modules\ModuleLink;

/**
 * The graph of modules as data, for larch:graph --json and the HTML report.
 * The format has a version; a change that breaks a reader raises it.
 */
final readonly class GraphExport
{
    public const int VERSION = 1;

    public function __construct(private Larch $larch) {}

    /**
     * @param  bool  $runtime  read the booted app too, not only the code
     * @return array{
     *     version: int,
     *     generator: string,
     *     kinds: list<string>,
     *     publicApi: list<string>,
     *     modules: list<array{name: string, namespace: string, classes: int}>,
     *     links: list<array{from: string, to: string, fromModule: string, toModule: string, kind: string, public: bool, places: list<array{file: string, line: int, through: string|null, boundIn: string|null}>}>,
     *     unknown: list<array{from: string, module: string, file: string, line: int, reason: string, boundIn: string|null}>
     * }
     */
    public function toArray(bool $runtime): array
    {
        $kinds = $runtime ? Edge::all()->kinds : [EdgeKind::Import];
        $graph = $runtime ? $this->larch->runtimeGraph() : $this->larch->graph();

        $modules = [];

        foreach ($this->larch->moduleMap() as $name => $namespace) {
            $classes = count(array_filter(
                array_keys($graph->classes),
                fn (string $class): bool => str_starts_with($class, $namespace.'\\'),
            ));

            $modules[] = ['name' => $name, 'namespace' => $namespace, 'classes' => $classes];
        }

        $links = array_map(fn (ModuleLink $link): array => [
            'from' => $link->fromClass,
            'to' => $link->toClass,
            'fromModule' => $link->fromModule,
            'toModule' => $link->toModule,
            'kind' => $link->kind->value,
            'public' => $this->larch->isPublic($link->toClass, $link->toModule),
            // Each place has its own facade or key, and where that is bound.
            'places' => array_map(fn ($edge): array => [
                'file' => $edge->file,
                'line' => $edge->line,
                'through' => $edge->through,
                'boundIn' => $edge->boundIn,
            ], $link->edges),
        ], $this->larch->links($kinds));

        $unknown = [];

        foreach ($graph->edgesOf([EdgeKind::Unknown]) as $edge) {
            $module = $this->larch->moduleOf($edge->from);

            if ($module !== null) {
                $unknown[] = [
                    'from' => $edge->from,
                    'module' => $module,
                    'file' => $edge->file,
                    'line' => $edge->line,
                    'reason' => (string) $edge->through,
                    'boundIn' => $edge->boundIn,
                ];
            }
        }

        return [
            'version' => self::VERSION,
            'generator' => 'nwidart/larch',
            'kinds' => array_map(fn (EdgeKind $kind): string => $kind->value, $kinds),
            'publicApi' => $this->larch->publicApiNamespaces(),
            'modules' => $modules,
            'links' => $links,
            'unknown' => $unknown,
        ];
    }

    public function toJson(bool $runtime): string
    {
        // A container key may hold bytes that are not UTF-8.
        return json_encode($this->toArray($runtime), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * The report of the mockups: the graph of modules, with kinds of link
     * to turn on and off, and every link with where it comes from.
     */
    public function toHtml(bool $runtime): string
    {
        $template = file_get_contents(dirname(__DIR__, 2).'/resources/report.html');

        if ($template === false) {
            return '';
        }

        // Safe inside <script>: no "</" can close the tag.
        $data = json_encode($this->toArray($runtime), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);

        return str_replace('/*LARCH_DATA*/null', $data, $template);
    }
}
