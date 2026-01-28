<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Console;

use Shopware\Core\Framework\Log\Package;

/**
 * Detects whether the current process is running in an interactive TTY environment.
 *
 * This service wraps the native stream_isatty() function to allow mocking in tests.
 *
 * Known edge cases:
 * - MinGW/MSYS (Git Bash): stream_isatty() returns false even when interactive
 *   because MinTTY connects via pipes. We detect MSYSTEM env var and assume TTY.
 *
 *   @see https://github.com/mintty/mintty/issues/56
 *
 * - Piped output: Commands like `php script.php | tee log.txt` return false even
 *   though output is visible in console. No workaround implemented.
 *
 * - IBMi PASE: isatty() always returns true for stdin/stdout/stderr (false positive).
 *   This is a rare platform and no workaround is implemented.
 *   @see https://github.com/nodejs/node/pull/30829
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class TtyDetector
{
    public function isStdinTty(): bool
    {
        // MinGW/MSYS (Git Bash) TTY detection is broken - assume interactive
        // @see https://github.com/symfony/symfony/blob/7.2/src/Symfony/Component/Console/Output/StreamOutput.php#L104
        if (\in_array(strtoupper((string) getenv('MSYSTEM')), ['MINGW32', 'MINGW64', 'MSYS'], true)) {
            return true;
        }

        return @stream_isatty(\STDIN);
    }
}
