<?php

declare(strict_types=1);

namespace Nwidart\Larch;

use Nwidart\Larch\Baseline\Baseline;
use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Parallel;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The Pest plugin: the --update-larch-baseline option, and the lines about
 * the baseline printed after the tests.
 *
 * @internal
 */
final readonly class Plugin implements AddsOutput, HandlesArguments
{
    public const string UPDATE_OPTION = '--update-larch-baseline';

    /**
     * Set by the option, and read by every Pest process of the run.
     */
    public const string UPDATE_VARIABLE = 'LARCH_UPDATE_BASELINE';

    /**
     * With --parallel, Pest starts its workers before this plugin sees the
     * arguments, and the workers do not get the option. So the option is
     * read here, when Pest loads its plugins, and passed to the workers
     * through the environment they inherit. Symfony Process, which starts
     * them, copies the variables of $_ENV.
     *
     * Only a parallel run sets the variable, and only a worker reads it, so
     * a Pest process that a test starts does not update its baseline.
     */
    public function __construct(private OutputInterface $output)
    {
        $argv = $_SERVER['argv'] ?? [];

        if (is_array($argv) && in_array(self::UPDATE_OPTION, $argv, true) && Parallel::isEnabled()) {
            putenv(self::UPDATE_VARIABLE.'=1');
            $_ENV[self::UPDATE_VARIABLE] = '1';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handleArguments(array $arguments): array
    {
        if (in_array(self::UPDATE_OPTION, $arguments, true) || (Parallel::isWorker() && getenv(self::UPDATE_VARIABLE) === '1')) {
            Baseline::update();
        }

        return array_values(array_filter($arguments, fn (string $argument): bool => $argument !== self::UPDATE_OPTION));
    }

    public function addOutput(int $exitCode): int
    {
        foreach (Baseline::summary() as $line) {
            $this->output->writeln('  '.$line);
        }

        return $exitCode;
    }
}
