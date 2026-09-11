<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\ConsentLog;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentConfigSnapshot;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentDecision;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Shopware\Core\Content\Cookie\ConsentLog\FilesystemCookieConsentLogStorage;
use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FilesystemCookieConsentLogStorage::class)]
class FilesystemCookieConsentLogStorageTest extends TestCase
{
    private Filesystem $filesystem;

    private FilesystemCookieConsentLogStorage $storage;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->storage = new FilesystemCookieConsentLogStorage($this->filesystem, '/cookie-consent/');
    }

    public function testADecisionBecomesOneFileInItsHourDirectory(): void
    {
        // Stored in UTC, so the same decision always lands in the same directory
        $record = $this->record('visitor-a', new \DateTimeImmutable('2026-07-13 14:02:57.270', new \DateTimeZone('Europe/Berlin')));

        $this->storage->log($record);

        $files = $this->files('cookie-consent/2026/07/13/12');
        static::assertCount(1, $files);
        static::assertMatchesRegularExpression('#^cookie-consent/2026/07/13/12/20260713T120257270Z\.[0-9a-f]{8}\.visitor-a\.json$#', $files[0]);
        static::assertEquals([$record], [...$this->storage->iterate(new \DateTimeImmutable('2026-07-13'), new \DateTimeImmutable('2026-07-14'))]);
    }

    public function testAConsentIdThatIsNotFileSafeIsRejected(): void
    {
        $this->expectExceptionObject(CookieException::invalidConsentId('../etc/passwd'));

        $this->storage->log($this->record('../etc/passwd', new \DateTimeImmutable('2026-07-13 12:00:00')));
    }

    public function testASnapshotIsStoredOncePerHash(): void
    {
        $createdAt = new \DateTimeImmutable('2026-07-13 12:00:00', new \DateTimeZone('UTC'));
        $this->storage->snapshot(new CookieConsentConfigSnapshot('hash', [['technicalName' => 'cookie.groupRequired']], $createdAt));
        // A later call with the same hash keeps the original file
        $this->storage->snapshot(new CookieConsentConfigSnapshot('hash', [], $createdAt->modify('+1 day')));

        static::assertSame(['cookie-consent/snapshots/hash.json'], $this->files('cookie-consent/snapshots'));
        static::assertSame(
            ['configHash' => 'hash', 'cookieGroups' => [['technicalName' => 'cookie.groupRequired']], 'createdAt' => '2026-07-13T12:00:00.000+00:00'],
            json_decode($this->filesystem->read('cookie-consent/snapshots/hash.json'), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    public function testCleanupDeletesExpiredHourDirectoriesAndEmptyParents(): void
    {
        $this->storage->log($this->record('old-year', new \DateTimeImmutable('2025-12-31 23:59:59')));
        $this->storage->log($this->record('old-hour', new \DateTimeImmutable('2026-03-14 10:59:59')));
        $this->storage->log($this->record('current-hour', new \DateTimeImmutable('2026-03-14 11:30:00')));
        $this->storage->log($this->record('future', new \DateTimeImmutable('2026-03-14 12:00:00')));
        $this->storage->snapshot(new CookieConsentConfigSnapshot('hash', [], new \DateTimeImmutable('2025-01-01')));

        // Falls inside the 11:00 hour: that directory is kept until the whole hour has expired
        $this->storage->cleanup(new \DateTimeImmutable('2026-03-14 11:45:00', new \DateTimeZone('UTC')));

        static::assertFalse($this->filesystem->directoryExists('cookie-consent/2025'));
        static::assertFalse($this->filesystem->directoryExists('cookie-consent/2026/03/14/10'));
        static::assertTrue($this->filesystem->directoryExists('cookie-consent/2026/03/14/11'));
        static::assertTrue($this->filesystem->directoryExists('cookie-consent/2026/03/14/12'));
        static::assertTrue($this->filesystem->fileExists('cookie-consent/snapshots/hash.json'));
        static::assertSame(['current-hour', 'future'], $this->consentIds($this->storage->iterate(new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2030-01-01'))));
    }

    public function testIterateFiltersByRangeAndSalesChannelInChronologicalOrder(): void
    {
        $this->storage->log($this->record('before-range', new \DateTimeImmutable('2026-06-30 23:59:59.999')));
        $this->storage->log($this->record('second', new \DateTimeImmutable('2026-07-01 00:00:00.500')));
        $this->storage->log($this->record('first', new \DateTimeImmutable('2026-07-01 00:00:00.100')));
        $this->storage->log($this->record('other-channel', new \DateTimeImmutable('2026-07-02 00:00:00'), salesChannelId: 'other-sales-channel'));
        $this->storage->log($this->record('at-upper-bound', new \DateTimeImmutable('2026-08-01 00:00:00')));

        $from = new \DateTimeImmutable('2026-07-01', new \DateTimeZone('UTC'));
        $to = new \DateTimeImmutable('2026-08-01', new \DateTimeZone('UTC'));

        static::assertSame(['first', 'second', 'other-channel'], $this->consentIds($this->storage->iterate($from, $to)));
        static::assertSame(['first', 'second'], $this->consentIds($this->storage->iterate($from, $to, 'sales-channel-id')));
    }

    /**
     * @param iterable<CookieConsentRecord> $records
     *
     * @return list<string>
     */
    private function consentIds(iterable $records): array
    {
        $consentIds = [];
        foreach ($records as $record) {
            $consentIds[] = $record->consentId;
        }

        return $consentIds;
    }

    /**
     * @return list<string>
     */
    private function files(string $directory): array
    {
        $files = [];
        foreach ($this->filesystem->listContents($directory) as $item) {
            if ($item->isFile()) {
                $files[] = $item->path();
            }
        }
        sort($files);

        return $files;
    }

    private function record(string $consentId, \DateTimeImmutable $createdAt, CookieConsentAction $action = CookieConsentAction::ACCEPT_ALL, string $salesChannelId = 'sales-channel-id'): CookieConsentRecord
    {
        return new CookieConsentRecord(
            consentId: $consentId,
            consentAction: $action,
            groupDecisions: ['cookie.groupRequired' => CookieConsentDecision::ACCEPTED, 'cookie.groupStatistical' => CookieConsentDecision::PARTIAL],
            acceptedCookies: ['lorem'],
            configHash: 'hash',
            salesChannelId: $salesChannelId,
            languageId: 'language-id',
            createdAt: $createdAt->getTimezone()->getName() === 'UTC' ? $createdAt : $createdAt->setTimezone(new \DateTimeZone('UTC')),
        );
    }
}
