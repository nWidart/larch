<?php

declare(strict_types=1);

namespace Nwidart\Larch;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Nwidart\Larch\Exceptions\LarchException;
use Nwidart\Larch\Graph\Graph;
use Nwidart\Larch\Graph\GraphBuilder;
use Nwidart\Larch\Modules\ModuleLink;

/**
 * The configuration of Larch for one test run, and the graph it builds.
 *
 * Configured once, usually in tests/Pest.php, through larch(). The graph is
 * built the first time a rule needs it and kept for the rest of the run.
 */
final class Larch
{
    private static ?self $instance = null;

    /** @var string|array<string, string>|null */
    private string|array|null $modules = null;

    /** @var list<string> */
    private array $publicApi = ['Contracts', 'Events'];

    /** @var list<string>|null */
    private ?array $paths = null;

    /** @var list<string> */
    private array $excluded = ['vendor', 'node_modules', 'tests', 'Tests'];

    private ?string $root = null;

    private string $baseline = 'larch-baseline.json';

    private ?Graph $graph = null;

    /** @var array<string, string>|null module name => namespace */
    private ?array $moduleMap = null;

    /** @var list<ModuleLink>|null */
    private ?array $links = null;

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * Forget the configuration and the graph. For Larch's own tests.
     *
     * @internal
     */
    public static function reset(): self
    {
        return self::$instance = new self;
    }

    /**
     * Name the modules of the app.
     *
     * A pattern such as 'Modules\*' makes each namespace directly under
     * Modules a module, named by that namespace. An array maps names to
     * namespaces. With no argument and nwidart/laravel-modules installed,
     * the pattern is 'Modules\*'.
     *
     * @param  string|array<string, string>|null  $modules
     */
    public function modules(string|array|null $modules = null): self
    {
        if (is_string($modules) && ! str_ends_with($modules, '\*')) {
            throw new LarchException("larch()->modules() takes a pattern that ends in \\*, such as 'Modules\\*', or an array of names and namespaces. It got '{$modules}'.");
        }

        $this->modules = $modules;

        return $this->forget();
    }

    /**
     * The namespaces, inside each module, that other modules may use.
     * Relative to the module: 'Contracts' means Modules\Billing\Contracts.
     */
    public function publicApi(string ...$namespaces): self
    {
        $this->publicApi = array_values(array_map(fn (string $namespace): string => trim($namespace, '\\'), $namespaces));

        return $this->forget();
    }

    /**
     * The folders to read, relative to the project root or absolute.
     * By default: every PSR-4 folder of the project outside vendor.
     */
    public function paths(string ...$paths): self
    {
        $this->paths = array_values($paths);

        return $this->forget();
    }

    /**
     * Folder names to skip wherever they appear. Replaces the default list:
     * vendor, node_modules, tests and Tests.
     */
    public function exclude(string ...$folders): self
    {
        $this->excluded = array_values($folders);

        return $this->forget();
    }

    /**
     * The project root. Paths in messages are shown relative to it.
     * By default: the folder that holds the vendor folder.
     */
    public function root(string $root): self
    {
        $this->root = rtrim($root, '/');

        return $this->forget();
    }

    /**
     * The baseline file, relative to the project root or absolute.
     * By default: larch-baseline.json at the project root.
     */
    public function baseline(string $path): self
    {
        $this->baseline = $path;

        return $this;
    }

    public function baselinePath(): string
    {
        return str_starts_with($this->baseline, '/') ? $this->baseline : $this->projectRoot().'/'.$this->baseline;
    }

    public function graph(): Graph
    {
        return $this->graph ??= (new GraphBuilder($this->projectRoot(), $this->excluded))->build($this->scanPaths());
    }

    /**
     * @return array<string, string> module name => namespace
     */
    public function moduleMap(): array
    {
        return $this->moduleMap ??= $this->discoverModules();
    }

    public function namespaceOf(string $module): string
    {
        $map = $this->moduleMap();

        if (! isset($map[$module])) {
            $known = $map === [] ? 'none' : implode(', ', array_keys($map));

            throw new LarchException("There is no module named {$module}. Modules found: {$known}.");
        }

        return $map[$module];
    }

    public function moduleOf(string $class): ?string
    {
        $found = null;
        $longest = 0;

        foreach ($this->moduleMap() as $name => $namespace) {
            if (str_starts_with($class, $namespace.'\\') && strlen($namespace) > $longest) {
                $found = $name;
                $longest = strlen($namespace);
            }
        }

        return $found;
    }

    public function isPublic(string $class, string $module): bool
    {
        $namespace = $this->namespaceOf($module);

        foreach ($this->publicApi as $api) {
            if (str_starts_with($class, $namespace.'\\'.$api.'\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function publicApiNamespaces(): array
    {
        return $this->publicApi;
    }

    /**
     * Every pair of classes in two different modules where the first uses
     * the second, one link per kind of edge, with the places it does.
     *
     * @return list<ModuleLink>
     */
    public function links(): array
    {
        if ($this->links !== null) {
            return $this->links;
        }

        $grouped = [];

        foreach ($this->graph()->edges as $edge) {
            $fromModule = $this->moduleOf($edge->from);
            $toModule = $this->moduleOf($edge->to);

            if ($fromModule === null || $toModule === null || $fromModule === $toModule) {
                continue;
            }

            $key = $edge->from.'|'.$edge->to.'|'.$edge->kind->value;
            $grouped[$key] ??= ['from' => $fromModule, 'to' => $toModule, 'edges' => []];
            $grouped[$key]['edges'][] = $edge;
        }

        ksort($grouped);

        return $this->links = array_values(array_map(
            fn (array $group): ModuleLink => new ModuleLink($group['from'], $group['to'], $group['edges']),
            $grouped,
        ));
    }

    /**
     * @return array<string, string>
     */
    private function discoverModules(): array
    {
        $modules = $this->modules;

        if ($modules === null) {
            if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled('nwidart/laravel-modules')) {
                throw new LarchException("Larch does not know the modules of this app. Call larch()->modules('App\\Modules\\*') in tests/Pest.php.");
            }

            $modules = 'Modules\*';
        }

        if (is_array($modules)) {
            return array_map(fn (string $namespace): string => trim($namespace, '\\'), $modules);
        }

        $prefix = trim(substr($modules, 0, -1), '\\').'\\';
        $map = [];

        foreach (array_keys($this->graph()->classes) as $class) {
            if (! str_starts_with($class, $prefix)) {
                continue;
            }

            $rest = substr($class, strlen($prefix));
            $separator = strpos($rest, '\\');

            if ($separator === false) {
                continue;
            }

            $name = substr($rest, 0, $separator);
            $map[$name] = $prefix.$name;
        }

        ksort($map);

        return $map;
    }

    /**
     * @return list<string>
     */
    private function scanPaths(): array
    {
        $root = $this->projectRoot();

        if ($this->paths !== null) {
            return array_map(
                fn (string $path): string => str_starts_with($path, '/') ? $path : $root.'/'.$path,
                $this->paths,
            );
        }

        $paths = [];

        foreach (ClassLoader::getRegisteredLoaders() as $vendorDir => $loader) {
            foreach ($loader->getPrefixesPsr4() as $directories) {
                foreach ($directories as $directory) {
                    $real = realpath($directory);

                    if ($real !== false && ! str_starts_with($real.'/', $vendorDir.'/')) {
                        $paths[] = $real;
                    }
                }
            }
        }

        if ($paths === []) {
            throw new LarchException('Larch found no PSR-4 folders outside vendor. Name the folders to read with larch()->paths().');
        }

        return array_values(array_unique($paths));
    }

    private function projectRoot(): string
    {
        if ($this->root !== null) {
            return $this->root;
        }

        $vendorDir = array_key_first(ClassLoader::getRegisteredLoaders());

        if ($vendorDir === null) {
            throw new LarchException('Larch could not find the project root. Set it with larch()->root().');
        }

        return dirname($vendorDir);
    }

    private function forget(): self
    {
        $this->graph = null;
        $this->moduleMap = null;
        $this->links = null;

        return $this;
    }
}
