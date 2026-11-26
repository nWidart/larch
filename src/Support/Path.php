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

    /**
     * Drops "." segments and repeated slashes: /app/./Modules//Audit is
     * /app/Modules/Audit. ".." stays, since a symlink can make it lead
     * elsewhere.
     */
    public static function tidy(string $path): string
    {
        $parts = array_filter(explode('/', $path), fn (string $part): bool => $part !== '' && $part !== '.');
        $tidy = (str_starts_with($path, '/') ? '/' : '').implode('/', $parts);

        return $tidy === '' ? '.' : $tidy;
    }
}
