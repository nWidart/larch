<?php

declare(strict_types=1);

namespace Nwidart\Larch\Exceptions;

use RuntimeException;

/**
 * Larch could not run: the configuration is wrong or a file cannot be read.
 * A broken rule is reported as a failed expectation, never as this.
 */
final class LarchException extends RuntimeException {}
