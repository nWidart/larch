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
        $edges = [];

        foreach ($this->files($paths) as $file) {
            $relative = $this->relative($file);
            $collector = $this->parse($file, $relative);

            foreach ($collector->declared as $class) {
                $classes[$class] = $relative;
            }

            foreach ($collector->references as $reference) {
                $edges[] = new Edge($reference['from'], $reference['to'], EdgeKind::Import, $relative, $reference['line']);
            }
        }

        ksort($classes);

        return new Graph($classes, $edges);
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

        $collector = new ReferenceCollector;
        $traverser = new NodeTraverser(new NameResolver, $collector);
        $traverser->traverse($ast);

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
