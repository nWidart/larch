<?php

declare(strict_types=1);

namespace Nwidart\Larch\Console;

use Illuminate\Console\Command;
use Nwidart\Larch\Exceptions\LarchException;
use Nwidart\Larch\Export\GraphExport;
use Nwidart\Larch\Larch;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * php artisan larch:graph: the graph of modules, as JSON or as an HTML report.
 *
 * Artisan does not read tests/Pest.php, so the modules, folders and public
 * API are options here, with the same defaults as larch().
 */
final class GraphCommand extends Command
{
    protected $signature = 'larch:graph
        {--json : Print the graph as JSON (the default)}
        {--html : Write the HTML report}
        {--output= : The file to write, instead of the output for JSON or larch-graph.html for HTML}
        {--modules= : The namespace pattern of the modules, such as App\\Modules\\*}
        {--paths=* : The folders to read, relative to the project}
        {--public-api=* : The public namespaces inside each module}
        {--imports-only : Read the code only, not the booted app}';

    protected $description = 'Export the graph of modules as JSON, or as an HTML report';

    public function handle(): int
    {
        try {
            $export = new GraphExport($this->larch());
            $runtime = ! $this->option('imports-only');
            $output = $this->option('output');
            $output = is_string($output) && $output !== '' ? $output : null;

            if ($this->option('html')) {
                return $this->write($output ?? 'larch-graph.html', $export->toHtml($runtime), 'The report is in');
            }

            $json = $export->toJson($runtime);
        } catch (LarchException $error) {
            $this->components->error($error->getMessage());

            return self::FAILURE;
        }

        if ($output !== null) {
            return $this->write($output, $json, 'The graph is in');
        }

        $this->output->write($json, false, OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }

    private function larch(): Larch
    {
        $larch = Larch::reset()->root($this->laravel->basePath());
        $modules = $this->option('modules');
        $paths = $this->listOption('paths');
        $publicApi = $this->listOption('public-api');

        if (is_string($modules) && $modules !== '') {
            $larch->modules($modules);
        }

        if ($paths !== []) {
            $larch->paths(...$paths);
        }

        if ($publicApi !== []) {
            $larch->publicApi(...$publicApi);
        }

        return $larch;
    }

    private function write(string $file, string $contents, string $message): int
    {
        $path = str_starts_with($file, '/') ? $file : $this->laravel->basePath($file);

        if (file_put_contents($path, $contents) === false) {
            $this->components->error("Larch could not write {$file}.");

            return self::FAILURE;
        }

        $this->components->info("{$message} {$file}.");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function listOption(string $name): array
    {
        $values = $this->option($name);

        return is_array($values) ? array_values(array_filter($values, fn (mixed $value): bool => is_string($value) && $value !== '')) : [];
    }
}
