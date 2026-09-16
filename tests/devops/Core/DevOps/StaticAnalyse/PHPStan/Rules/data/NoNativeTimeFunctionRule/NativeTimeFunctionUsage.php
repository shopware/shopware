<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoNativeTimeFunctionRule;

class NativeTimeFunctionUsage
{
    private const NOW_CONSTANT = 'now';

    public function alwaysFlagged(): void
    {
        \time();
        \microtime(true);
        \hrtime();
        \hrtime(true);
    }

    public function strtotimeCurrentTimeReads(): void
    {
        \strtotime('now');
        \strtotime('');
        \strtotime('+1 day');
        \strtotime('tomorrow');
        \strtotime('10:30');
    }

    public function strtotimeNullBase(): void
    {
        // explicit null base is equivalent to the default — still reads time()
        \strtotime('+1 day', null);
    }

    public function allowedNonLiteral(string $s): void
    {
        // not flagged — non-literal arguments are accepted under the loose policy
        \strtotime($s);
        \strtotime(self::NOW_CONSTANT);
    }

    public function allowedAbsolute(): void
    {
        // not flagged — literal absolute or anchored strings
        \strtotime('2024-01-15');
        \strtotime('2024-01-15 10:30:00');
        \strtotime('2024-01-15T10:30:00+00:00');
        \strtotime('@1700000000');
        \strtotime('2024-01-15 +1 day');
    }

    public function allowedExplicitBase(int $base, ?int $maybeBase): void
    {
        // not flagged — any base other than a definitely null literal is trusted
        \strtotime('+1 day', $base);
        \strtotime('now', 1700000000);
        \strtotime('tomorrow', $base);
        \strtotime('+1 day', $maybeBase);
    }

    public function allowedFunctions(): void
    {
        // not in the banlist
        \date('Y-m-d', 0);
    }

    public function microtimeNoArgs(): void
    {
        // bare microtime() with no args — still a wall-clock read
        \microtime();
    }

    public function strtotimeAbsoluteWithNullBase(): void
    {
        // literal null base falls through to the string check, but the absolute
        // datetime string is not a current-time read — so no flag
        \strtotime('2024-01-15', null);
    }

    public function strtotimeNamedArgs(int $base): void
    {
        \strtotime(datetime: '+1 day');
        \strtotime(datetime: 'tomorrow', baseTimestamp: $base);
        \strtotime(baseTimestamp: null, datetime: '+1 day');
        \strtotime(datetime: '2024-01-15');
    }

    public function dateCreateFactory(string $s, \DateTimeZone $tz): void
    {
        // zero args default to "now"
        \date_create();
        \date_create_immutable();

        // literal current-time strings
        \date_create('now');
        \date_create_immutable('+1 hour');

        // named args: timezone-only falls back to "now"; datetime: is literal
        \date_create(timezone: $tz);
        \date_create(datetime: 'tomorrow');

        // not flagged — literal absolute strings
        \date_create('2024-01-15');
        \date_create_immutable('@1700000000');

        // not flagged — dynamic input under the loose policy
        \date_create($s);
    }
}
