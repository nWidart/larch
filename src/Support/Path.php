<?php

declare(strict_types=1);

namespace Nwidart\Larch\Support;

/**
 * @internal
 */
final class Path
{
    /**
     * /path, C:/path, C:\path and \\server\share are absolute.
     */
    public static function isAbsolute(string $path): bool
    {
        return preg_match('~^(/|[A-Za-z]:[/\\\\]|\\\\\\\\)~', $path) === 1;
    }
}
