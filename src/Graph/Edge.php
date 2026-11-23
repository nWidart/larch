<?php

declare(strict_types=1);

namespace Nwidart\Larch\Graph;

/**
 * One place where a class reaches another class.
 */
final readonly class Edge
{
    /**
     * @param  string  $to  empty for an unknown edge
     * @param  string|null  $through  how the link is made, when it is not an import: "the facade App\Facades\Stock"
     * @param  string|null  $boundIn  where the app registers the link: "app/Providers/AppServiceProvider.php:28"
     */
    public function __construct(
        public string $from,
        public string $to,
        public EdgeKind $kind,
        public string $file,
        public int $line,
        public ?string $through = null,
        public ?string $boundIn = null,
    ) {}
}
