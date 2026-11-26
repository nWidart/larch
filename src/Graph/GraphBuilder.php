<?php

declare(strict_types=1);

namespace Nwidart\Larch\Graph;

use FilesystemIterator;
use Nwidart\Larch\Exceptions\LarchException;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Reads the PHP files under a set of folders and builds the graph of links
 * between the classes they declare. Nothing is loaded or run.
 */
final class GraphBuilder
{
    private readonly Parser $parser;

    /**
     * @param  list<string>  $excludedFolders  folder names skipped wherever they appear
     */
    public function __construct(
        private readonly string $root,
        private readonly array $excludedFolders,
    ) {
        $this->parser = (new ParserFactory)->createForHostVersion();
    }

    /**
     * @param  list<string>  $paths  absolute folders or files
     */
    public function build(array $paths): Graph
    {
        $classes = [];
        $lines = [];
        $edges = [];
        $calls = [];
        $bindings = [];
        $morphs = [];

        foreach ($this->files($paths) as $file) {
            $relative = $this->relative($file);
            $collector = $this->parse($file, $relative);

            foreach ($collector->declared as $class) {
                $classes[$class] = $relative;
            }

            $lines += $collector->lines;

            foreach ($collector->references as $reference) {
                $edges[] = new Edge($reference['from'], $reference['to'], EdgeKind::Import, $relative, $reference['line']);
            }

            foreach ($collector->calls as $call) {
                $calls[] = new CallSite($call['type'], $call['from'], $call['target'], $call['expression'], $relative, $call['line']);
            }

            $morphs += $collector->morphs;

            foreach ($collector->bindings as $key => $line) {
                $bindings[$key] ??= $relative.':'.$line;
            }
        }

        ksort($classes);
        ksort($morphs);

        return new Graph($classes, self::withoutNamespaces($edges, $classes), $calls, $bindings, $lines, array_keys($morphs));
    }

    /**
     * Leaves out the links to a namespace, such as an unused
     * use Modules\Billing\Contracts;: the code declares no class of that
     * name, but declares classes inside it. Mortise leaves them out too.
     *
     * @param  list<Edge>  $edges
     * @param  array<string, string>  $classes
     * @return list<Edge>
     */
    private static function withoutNamespaces(array $edges, array $classes): array
    {
        $namespaces = [];

        foreach (array_keys($classes) as $class) {
            $parts = explode('\\', $class);

            while (count($parts) > 1) {
                array_pop($parts);
                $namespaces[implode('\\', $parts)] = true;
            }
        }

        return array_values(array_filter(
            $edges,
            fn (Edge $edge): bool => isset($classes[$edge->to]) || ! isset($namespaces[$edge->to]),
        ));
    }

    private function parse(string $file, string $relative): ReferenceCollector
    {
        $code = file_get_contents($file);

        if ($code === false) {
            throw new LarchException("Larch could not read {$relative}.");
        }

        try {
            $ast = $this->parser->parse($code) ?? [];
        } catch (Error $error) {
            throw new LarchException("Larch could not parse {$relative}: {$error->getMessage()}. Fix the file or exclude its folder with larch()->exclude().", previous: $error);
        }

        // Names are resolved in a first pass, so the collector sees the
        // arguments of a call resolved when it enters the call.
        $ast = (new NodeTraverser(new NameResolver))->traverse($ast);
        $collector = new ReferenceCollector($code);
        (new NodeTraverser($collector))->traverse($ast);

        return $collector;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function files(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;

                continue;
            }

            if (! is_dir($path)) {
                throw new LarchException("Larch could not find the folder {$this->relative($path)}. Check larch()->paths().");
            }

            $directory = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
            $filter = new RecursiveCallbackFilterIterator(
                $directory,
                fn (SplFileInfo $item): bool => $item->isDir()
                    ? ! in_array($item->getFilename(), $this->excludedFolders, true)
                    : $item->getExtension() === 'php',
            );

            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator($filter) as $file) {
                $files[] = $file->getPathname();
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    private function relative(string $path): string
    {
        $root = rtrim($this->root, '/').'/';

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}
