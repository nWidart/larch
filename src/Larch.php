<?php

declare(strict_types=1);

namespace Nwidart\Larch;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Nwidart\Larch\Exceptions\LarchException;
use Nwidart\Larch\Graph\EdgeKind;
use Nwidart\Larch\Graph\Graph;
use Nwidart\Larch\Graph\GraphBuilder;
use Nwidart\Larch\Modules\ModuleLink;
use Nwidart\Larch\Runtime\RuntimePass;
use Nwidart\Larch\Subjects\Jobs;
use Nwidart\Larch\Subjects\Listeners;
use Nwidart\Larch\Subjects\Models;
use Nwidart\Larch\Subjects\Routes;

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

    private ?Graph $runtimeGraph = null;

    /** The app $runtimeGraph was read from. */
    private ?Container $runtimeApp = null;

    /** @var array<string, string>|null module name => namespace */
    private ?array $moduleMap = null;

    /** @var array<string, list<ModuleLink>> kinds => links */
    private array $links = [];

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
        $this->root = rtrim(realpath($root) ?: $root, '/');

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
        // /path, C:/path, C:\path and \\server\share are absolute.
        return preg_match('~^(/|[A-Za-z]:[/\\\\]|\\\\\\\\)~', $this->baseline) === 1
            ? $this->baseline
            : $this->projectRoot().'/'.$this->baseline;
    }

    /**
     * The graph read from the code: import edges, and the call sites the
     * runtime pass resolves.
     */
    public function graph(): Graph
    {
        return $this->graph ??= (new GraphBuilder($this->projectRoot(), $this->excluded))->build($this->scanPaths());
    }

    /**
     * The graph with the edges of the booted app added: facades, container
     * keys, listeners, jobs and unknown edges. Boots the app if no test has.
     * Read again when a test boots another app.
     */
    public function runtimeGraph(): Graph
    {
        $app = $this->application();

        if ($this->runtimeGraph === null || $this->runtimeApp !== $app) {
            $this->runtimeApp = $app;
            $this->links = [];
            $this->runtimeGraph = $this->graph()->withEdges(
                (new RuntimePass($app, $this->graph(), $this->projectRoot()))->edges(),
            );
        }

        return $this->runtimeGraph;
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

    /**
     * Whether the class is in one of the public namespaces of its module.
     * PHP namespaces ignore case, and so does this.
     *
     * @param  list<string>|null  $publicApi  instead of the namespaces set with publicApi()
     */
    public function isPublic(string $class, string $module, ?array $publicApi = null): bool
    {
        $namespace = $this->namespaceOf($module);

        foreach ($publicApi ?? $this->publicApi as $api) {
            $prefix = $namespace.'\\'.trim($api, '\\').'\\';

            if (strncasecmp($class, $prefix, strlen($prefix)) === 0) {
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
     * @param  list<EdgeKind>  $kinds  only edges of these kinds
     * @return list<ModuleLink>
     */
    public function links(array $kinds = [EdgeKind::Import]): array
    {
        $key = implode(',', array_map(fn (EdgeKind $kind): string => $kind->value, $kinds));
        $runtime = array_filter($kinds, fn (EdgeKind $kind): bool => $kind !== EdgeKind::Import) !== [];
        $graph = $runtime ? $this->runtimeGraph() : $this->graph();

        if (isset($this->links[$key])) {
            return $this->links[$key];
        }

        $grouped = [];

        foreach ($graph->edgesOf($kinds) as $edge) {
            $fromModule = $this->moduleOf($edge->from);
            $toModule = $this->moduleOf($edge->to);

            if ($fromModule === null || $toModule === null || $fromModule === $toModule) {
                continue;
            }

            $key = $edge->from.'|'.$edge->to."\0".$edge->kind->value;
            $grouped[$key] ??= ['from' => $fromModule, 'to' => $toModule, 'edges' => []];
            $grouped[$key]['edges'][] = $edge;
        }

        ksort($grouped);

        return $this->links[$key] = array_values(array_map(
            fn (array $group): ModuleLink => new ModuleLink($group['from'], $group['to'], $group['edges']),
            $grouped,
        ));
    }

    /**
     * The routes of the booted app.
     */
    public function routes(): Routes
    {
        return new Routes;
    }

    /**
     * The Eloquent models the code declares.
     */
    public function models(): Models
    {
        return new Models;
    }

    /**
     * The jobs the code declares: classes that use the Bus Dispatchable trait.
     */
    public function jobs(): Jobs
    {
        return new Jobs;
    }

    /**
     * The listeners of the events that match the patterns:
     * listenersOf('Modules\Billing\Events\*').
     */
    public function listenersOf(string ...$events): Listeners
    {
        if ($events === []) {
            throw new LarchException("larch()->listenersOf() takes at least one event pattern, such as 'App\\Events\\*'.");
        }

        return new Listeners(array_values(array_map(fn (string $event): string => ltrim($event, '\\'), $events)));
    }

    /**
     * The app the runtime pass reads: the one a Laravel test case booted,
     * or else the app of bootstrap/app.php, booted here in the testing
     * environment.
     *
     * @internal
     */
    public function application(): Container
    {
        if (! class_exists(Container::class)) {
            throw new LarchException('Larch reads facades, the container, events and jobs from a Laravel app, and Laravel is not installed. Use via(Edge::imports()).');
        }

        $current = Container::getInstance();

        if ($current instanceof Application && $current->isBooted()) {
            return $current;
        }

        $bootstrap = $this->projectRoot().'/bootstrap/app.php';

        if (! is_file($bootstrap)) {
            throw new LarchException("Larch needs the booted app to read facades, the container, events and jobs. Run the test with the Laravel test case, or add {$bootstrap}.");
        }

        // Boot in the testing environment, whatever the process says, and
        // without a cached configuration, which may be the production one.
        $restore = $this->setEnvironment([
            'APP_ENV' => 'testing',
            'APP_CONFIG_CACHE' => sys_get_temp_dir().'/larch-no-config-cache-'.getmypid().'.php',
        ]);

        try {
            $app = require $bootstrap;

            if (! $app instanceof Application) {
                throw new LarchException("{$bootstrap} does not return a Laravel application.");
            }

            $app->make(Kernel::class)->bootstrap();
        } finally {
            $this->setEnvironment($restore);
        }

        return $app;
    }

    /**
     * Set environment variables, and return their previous values (null for
     * a variable that was not set) to restore them.
     *
     * @param  array<string, string|null>  $variables
     * @return array<string, string|null>
     */
    private function setEnvironment(array $variables): array
    {
        $previous = [];

        foreach ($variables as $name => $value) {
            $old = getenv($name);
            $previous[$name] = $old === false ? null : $old;

            if ($value === null) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);
            } else {
                putenv("{$name}={$value}");
                $_ENV[$name] = $_SERVER[$name] = $value;
            }
        }

        return $previous;
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

    public function projectRoot(): string
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
        $this->runtimeGraph = null;
        $this->runtimeApp = null;
        $this->moduleMap = null;
        $this->links = [];

        return $this;
    }
}
