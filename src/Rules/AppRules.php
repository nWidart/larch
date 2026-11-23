<?php

declare(strict_types=1);

namespace Nwidart\Larch\Rules;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Nwidart\Larch\Baseline\Fingerprint;
use Nwidart\Larch\Larch;
use Nwidart\Larch\Subjects\Jobs;
use Nwidart\Larch\Subjects\Listeners;
use Nwidart\Larch\Subjects\Models;
use Nwidart\Larch\Subjects\Routes;

/**
 * The rules on how the app is wired: routes, models, jobs and listeners.
 * Each reads the booted app and returns what it found as a Result.
 */
final readonly class AppRules
{
    public function __construct(private Larch $larch) {}

    /**
     * Every route runs each of the middleware. A name, an alias with its
     * parameters ('auth:sanctum'), a group ('api') or a class.
     *
     * @param  list<string>  $middleware
     * @return Result<Finding>
     */
    public function useMiddleware(Routes $routes, array $middleware): Result
    {
        $app = $this->larch->application();

        // The HTTP kernel gives the router its middleware aliases and groups.
        // A test boots the console kernel, so the HTTP one may not exist yet.
        if ($app->bound(HttpKernel::class)) {
            $app->make(HttpKernel::class);
        }

        $router = $app->make('router');

        if (! $router instanceof Router) {
            return new Result([], fn (): string => '');
        }

        // A group is checked by name: groups share members, such as
        // SubstituteBindings, so their classes do not tell them apart.
        $groups = $router->getMiddlewareGroups();
        $findings = [];

        foreach ($routes->resolve($app) as $route) {
            $declared = $this->strings($route->gatherMiddleware());
            $present = $this->strings($router->gatherRouteMiddleware($route));
            $missing = array_values(array_filter(
                $middleware,
                fn (string $one): bool => isset($groups[$one])
                    ? ! in_array($one, $declared, true)
                    : array_filter($this->strings($router->resolveMiddleware([$one])), fn (string $class): bool => ! $this->runs($class, $present)) !== [],
            ));

            if ($missing === []) {
                continue;
            }

            $place = $this->routeName($route);

            $findings[] = new Finding($place, [
                (string) $route->getActionName(),
                'Middleware: '.($declared === [] ? 'none' : implode(', ', $declared)),
            ], new Fingerprint($place, implode(', ', $middleware), 'middleware'));
        }

        $list = implode(', ', $middleware);

        return new Result($findings, fn (array $new): string => Report::findings(
            "{$routes->describe()} use the middleware {$list}.", 'route', 'routes', $new,
        ));
    }

    /**
     * Every model has a policy, registered or found by Laravel's naming.
     *
     * @return Result<Finding>
     */
    public function havePolicy(Models $models): Result
    {
        $gate = $this->larch->application()->make(Gate::class);
        $findings = [];

        foreach ($models->resolve($this->larch) as $model) {
            if ($gate->getPolicyFor($model) !== null) {
                continue;
            }

            $findings[] = new Finding($this->place($model), [
                "{$model} has no policy. Register one with Gate::policy(), or add a policy Laravel finds by name.",
            ], new Fingerprint($model, '', 'policy'));
        }

        return new Result($findings, fn (array $new): string => Report::findings(
            "{$models->describe()} have a policy.", 'model', 'models', $new,
        ));
    }

    /**
     * Every job declares each member, as a property or a method:
     * tries, timeout, backoff, maxExceptions.
     *
     * @param  list<string>  $members
     * @return Result<Finding>
     */
    public function define(Jobs $jobs, array $members): Result
    {
        $findings = [];

        foreach ($jobs->resolve($this->larch) as $job) {
            $missing = array_values(array_filter(
                $members,
                fn (string $member): bool => ! property_exists($job, $member) && ! method_exists($job, $member),
            ));

            if ($missing === []) {
                continue;
            }

            $findings[] = new Finding($this->place($job), [
                "{$job} does not define ".implode(', ', $missing).'.',
            ], new Fingerprint($job, implode(', ', $missing), 'define'));
        }

        $list = implode(', ', $members);

        return new Result($findings, fn (array $new): string => Report::findings(
            "{$jobs->describe()} define {$list}.", 'job', 'jobs', $new,
        ));
    }

    /**
     * Every listener implements ShouldQueue.
     *
     * @return Result<Finding>
     */
    public function beQueued(Listeners $listeners): Result
    {
        $findings = [];

        foreach ($listeners->resolve($this->larch->application()) as ['listener' => $listener, 'event' => $event]) {
            if (is_subclass_of($listener, ShouldQueue::class)) {
                continue;
            }

            $findings[] = new Finding($this->place($listener), [
                "{$listener} listens to {$event} and is not queued. Implement ShouldQueue.",
            ], new Fingerprint($listener, $event, 'queued'));
        }

        return new Result($findings, fn (array $new): string => Report::findings(
            "{$listeners->describe()} are queued.", 'listener', 'listeners', $new,
        ));
    }

    /**
     * Every model has an alias in the morph map, so the database stores
     * the alias and not the class name.
     *
     * @return Result<Finding>
     */
    public function beInMorphMap(Models $models): Result
    {
        // Booting the app registers the morph map.
        $this->larch->application();
        $findings = [];

        foreach ($models->resolve($this->larch) as $model) {
            if (Relation::getMorphAlias($model) !== $model) {
                continue;
            }

            $findings[] = new Finding($this->place($model), [
                "{$model} has no alias in the morph map. Add it to Relation::morphMap() or Relation::enforceMorphMap().",
            ], new Fingerprint($model, '', 'morph-map'));
        }

        return new Result($findings, fn (array $new): string => Report::findings(
            "{$models->describe()} are in the morph map.", 'model', 'models', $new,
        ));
    }

    /**
     * Whether a resolved middleware runs, alone or with parameters.
     *
     * @param  list<string>  $present
     */
    private function runs(string $middleware, array $present): bool
    {
        foreach ($present as $one) {
            if ($one === $middleware || (! str_contains($middleware, ':') && str_starts_with($one, $middleware.':'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $middleware
     * @return list<string>
     */
    private function strings(array $middleware): array
    {
        return array_values(array_filter($middleware, 'is_string'));
    }

    private function routeName(Route $route): string
    {
        $methods = array_values(array_diff($this->strings($route->methods()), ['HEAD']));

        return implode('|', $methods).' '.$route->uri();
    }

    private function place(string $class): string
    {
        $graph = $this->larch->graph();

        if (! isset($graph->classes[$class])) {
            return $class;
        }

        $line = $graph->lines[$class] ?? 0;

        return $line > 0 ? $graph->classes[$class].':'.$line : $graph->classes[$class];
    }
}
