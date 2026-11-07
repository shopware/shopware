<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Services\VersionHistory;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(VersionHistory::class)]
class VersionHistoryTest extends TestCase
{
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-04T10:00:00+00:00');
    }

    public function testRecordsAnInstall(): void
    {
        $versionHistory = new VersionHistory(new ArrayKeyValueStorage(), $this->clock, '6.7.17.0');

        $versionHistory->record(installed: true);

        static::assertSame([
            ['version' => '6.7.17.0', 'recordedAt' => '2026-11-04T10:00:00+00:00', 'installed' => true],
        ], $versionHistory->all());
    }

    public function testAppendsAnUpdateToTheStoredHistoryOnlyOnce(): void
    {
        $storage = new ArrayKeyValueStorage([
            VersionHistory::STORAGE_KEY => json_encode([
                ['version' => '6.7.16.0', 'recordedAt' => '2026-10-01T10:00:00+00:00', 'installed' => true],
            ]),
        ]);
        $versionHistory = new VersionHistory($storage, $this->clock, '6.7.17.0');

        $versionHistory->record(installed: false);
        $this->clock->modify('+1 hour');
        $versionHistory->record(installed: false);

        static::assertSame([
            ['version' => '6.7.16.0', 'recordedAt' => '2026-10-01T10:00:00+00:00', 'installed' => true],
            ['version' => '6.7.17.0', 'recordedAt' => '2026-11-04T10:00:00+00:00', 'installed' => false],
        ], $versionHistory->all());
    }

    public function testKeepsTheFirstArrivalOfAVersionAfterADowngrade(): void
    {
        $storage = new ArrayKeyValueStorage([
            VersionHistory::STORAGE_KEY => json_encode([
                ['version' => '6.7.16.0', 'recordedAt' => '2026-09-01T10:00:00+00:00', 'installed' => true],
                ['version' => '6.7.17.0', 'recordedAt' => '2026-10-01T10:00:00+00:00', 'installed' => false],
            ]),
        ]);

        (new VersionHistory($storage, $this->clock, '6.7.16.0'))->record(installed: false);
        $versionHistory = new VersionHistory($storage, $this->clock, '6.7.17.0');
        $versionHistory->record(installed: false);

        static::assertSame([
            ['version' => '6.7.16.0', 'recordedAt' => '2026-09-01T10:00:00+00:00', 'installed' => true],
            ['version' => '6.7.17.0', 'recordedAt' => '2026-10-01T10:00:00+00:00', 'installed' => false],
        ], $versionHistory->all());
    }

    public function testStartsAFreshHistoryOnAReinstall(): void
    {
        $storage = new ArrayKeyValueStorage([
            VersionHistory::STORAGE_KEY => json_encode([
                ['version' => '6.7.16.0', 'recordedAt' => '2026-09-01T10:00:00+00:00', 'installed' => true],
                ['version' => '6.7.17.0', 'recordedAt' => '2026-10-01T10:00:00+00:00', 'installed' => false],
            ]),
        ]);
        $versionHistory = new VersionHistory($storage, $this->clock, '6.7.17.0');

        $versionHistory->record(installed: true);

        static::assertSame([
            ['version' => '6.7.17.0', 'recordedAt' => '2026-11-04T10:00:00+00:00', 'installed' => true],
        ], $versionHistory->all());
    }

    public function testReadsAnUnreadableStoredValueAsAnEmptyHistory(): void
    {
        $versionHistory = new VersionHistory(
            new ArrayKeyValueStorage([VersionHistory::STORAGE_KEY => '{not json']),
            $this->clock,
            '6.7.17.0',
        );

        static::assertSame([], $versionHistory->all());
    }

    public function testKeepsTheLast24Entries(): void
    {
        $entries = array_map(
            static fn (int $minor) => ['version' => '6.6.' . $minor . '.0', 'recordedAt' => '2024-01-01T10:00:00+00:00', 'installed' => false],
            range(0, 23),
        );
        $storage = new ArrayKeyValueStorage([VersionHistory::STORAGE_KEY => $entries]);
        $versionHistory = new VersionHistory($storage, $this->clock, '6.7.17.0');

        $versionHistory->record(installed: false);

        $history = $versionHistory->all();
        static::assertCount(24, $history);
        static::assertSame('6.6.1.0', $history[0]['version']);
        static::assertSame('6.7.17.0', $history[23]['version']);
    }
}
