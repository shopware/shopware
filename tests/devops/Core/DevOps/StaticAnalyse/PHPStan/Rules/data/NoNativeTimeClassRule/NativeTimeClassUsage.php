<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoNativeTimeClassRule;

class NativeTimeClassUsage
{
    private const NOW_CONSTANT = 'now';

    public function zeroArgs(): void
    {
        new \DateTime();
        new \DateTimeImmutable();
    }

    public function nowLiteral(): void
    {
        new \DateTime('now');
        new \DateTimeImmutable('NOW');
    }

    public function emptyString(): void
    {
        new \DateTime('');
    }

    public function relativeOffsets(): void
    {
        new \DateTime('+1 hour');
        new \DateTime('-5 minutes');
        new \DateTime('tomorrow');
        new \DateTime('yesterday');
        new \DateTime('next monday');
    }

    public function timeOnly(): void
    {
        new \DateTime('10:30');
    }

    /**
     * @param array<string, mixed> $row
     */
    public function allowedNonLiteral(string $s, array $row): void
    {
        // not flagged — non-literal arguments are accepted under the loose policy
        new \DateTime($s);
        new \DateTimeImmutable($s);
        new \DateTime((string) $row['date']);
        new \DateTime(self::NOW_CONSTANT);
    }

    public function allowedAbsolute(): void
    {
        // not flagged — literal absolute or anchored strings
        new \DateTime('2024-01-15');
        new \DateTime('2024-01-15 10:30:00');
        new \DateTime('2024-01-15T10:30:00+00:00');
        new \DateTimeImmutable('@1700000000');
        new \DateTime('2024-01-15 +1 day');
    }

    public function namedArgs(\DateTimeZone $tz): void
    {
        // PHP 8 named-argument syntax — same policy as positional
        new \DateTime(datetime: 'now');
        new \DateTime(timezone: $tz);
        new \DateTime(datetime: '2024-01-15');
    }
}
