<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Shared string heuristic used by NoNativeTimeClassRule and
 * NoNativeTimeFunctionRule to decide whether a datetime-string argument would
 * cause a DateTime / strtotime call to read the current time.
 */
#[Package('framework')]
final class NativeTimeReadHelper
{
    /**
     * Determines whether the given datetime string would cause a DateTime
     * constructor or strtotime() call to read the current time. A string is
     * considered safe only when it carries a complete date (no relative-only
     * offsets) or is a Unix-timestamp literal "@<int>".
     */
    public static function readsCurrentTime(string $input): bool
    {
        $trimmed = trim($input);

        if ($trimmed === '' || strtolower($trimmed) === 'now') {
            return true;
        }

        // Unix timestamp form '@<int>[.frac]' — fixed instant, no current-time read.
        if (preg_match('/^@-?\d+(\.\d+)?$/', $trimmed) === 1) {
            return false;
        }

        $parsed = date_parse($trimmed);
        if ($parsed['error_count'] > 0) {
            // Unparseable strings will throw at runtime — not our concern here.
            return false;
        }

        $hasDate = $parsed['year'] !== false
            && $parsed['month'] !== false
            && $parsed['day'] !== false;

        // Relative parts ("+1 hour", "tomorrow") without an anchoring date pull
        // from "now". With a date anchor they're safe ("2024-01-15 +1 day").
        if (isset($parsed['relative']) && !$hasDate) {
            return true;
        }

        // A string with no date component (e.g. "10:30") gets today's date filled in.
        return !$hasDate;
    }
}
