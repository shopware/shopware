<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Cookie\ConsentLog;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentConfigSnapshot;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentDecision;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentSource;
use Shopware\Core\Content\Cookie\ConsentLog\DatabaseCookieConsentLogStorage;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class DatabaseCookieConsentLogStorageTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const OTHER_SALES_CHANNEL = '0189f0c6d9a273f8a4e3c4b1d5f2e6a7';

    private Connection $connection;

    private DatabaseCookieConsentLogStorage $storage;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->storage = new DatabaseCookieConsentLogStorage($this->connection);
    }

    public function testARecordSurvivesTheRoundTrip(): void
    {
        $record = $this->record('visitor-a', new \DateTimeImmutable('2026-07-13 12:00:00.123'), [
            'consentAction' => CookieConsentAction::ACCEPT_SELECTED,
            'groupDecisions' => [
                'cookie.groupRequired' => CookieConsentDecision::ACCEPTED,
                'cookie.groupStatistical' => CookieConsentDecision::PARTIAL,
            ],
            'acceptedCookies' => ['lorem'],
        ]);

        $this->storage->log($record);

        static::assertEquals([$record], $this->storage->findByConsentId('visitor-a'));
        static::assertSame([], $this->storage->findByConsentId('visitor-b'));
    }

    public function testDecisionsOfAVisitorAreReturnedOldestFirst(): void
    {
        $this->storage->log($this->record('visitor-a', new \DateTimeImmutable('2026-07-13 12:00:00'), ['consentAction' => CookieConsentAction::ACCEPT_ALL]));
        $this->storage->log($this->record('visitor-b', new \DateTimeImmutable('2026-07-13 12:00:00')));
        $this->storage->log($this->record('visitor-a', new \DateTimeImmutable('2026-07-10 12:00:00'), ['consentAction' => CookieConsentAction::ACCEPT_REQUIRED]));

        $records = $this->storage->findByConsentId('visitor-a');

        static::assertSame(
            [CookieConsentAction::ACCEPT_REQUIRED, CookieConsentAction::ACCEPT_ALL],
            array_map(static fn (CookieConsentRecord $record) => $record->consentAction, $records),
        );
    }

    public function testASnapshotIsStoredOncePerHash(): void
    {
        $group = new CookieGroup('cookie.groupStatistical');
        $group->name = 'Statistics';
        $group->setEntries(new CookieEntryCollection([new CookieEntry('lorem')]));

        $first = new CookieConsentConfigSnapshot('hash', [$group], new \DateTimeImmutable('2026-07-13 12:00:00'));
        $this->storage->snapshot($first);
        // A later call with the same hash keeps the original row
        $this->storage->snapshot(new CookieConsentConfigSnapshot('hash', [], new \DateTimeImmutable('2026-07-14 12:00:00')));

        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `cookie_consent_config_snapshot`'));

        $snapshot = $this->storage->findSnapshot('hash');
        static::assertNotNull($snapshot);
        static::assertSame('hash', $snapshot->configHash);
        static::assertSame('2026-07-13 12:00:00', $snapshot->createdAt->format('Y-m-d H:i:s'));
        static::assertCount(1, $snapshot->cookieGroups);
        static::assertSame('cookie.groupStatistical', $snapshot->cookieGroups[0]['technicalName']);
        static::assertSame('Statistics', $snapshot->cookieGroups[0]['name']);
        static::assertSame('lorem', $snapshot->cookieGroups[0]['entries'][0]['cookie']);

        static::assertNull($this->storage->findSnapshot('unknown'));
    }

    public function testCleanupDeletesOldDecisionsButKeepsSnapshots(): void
    {
        $this->storage->snapshot(new CookieConsentConfigSnapshot('old-hash', [], new \DateTimeImmutable('2025-01-01 12:00:00')));
        $this->storage->log($this->record('expired', new \DateTimeImmutable('2026-03-14 11:59:59.999')));
        $this->storage->log($this->record('kept', new \DateTimeImmutable('2026-03-14 12:00:00.000')));

        $this->storage->cleanup(new \DateTimeImmutable('2026-03-14 12:00:00'));

        static::assertSame(['kept'], $this->connection->fetchFirstColumn('SELECT `consent_id` FROM `cookie_consent_log`'));
        static::assertNotNull($this->storage->findSnapshot('old-hash'));
    }

    public function testIterateFiltersByRangeAndSalesChannel(): void
    {
        $this->storage->log($this->record('before-range', new \DateTimeImmutable('2026-06-30 23:59:59.999')));
        $this->storage->log($this->record('in-range', new \DateTimeImmutable('2026-07-01 00:00:00')));
        $this->storage->log($this->record('other-channel', new \DateTimeImmutable('2026-07-02 00:00:00'), ['salesChannelId' => self::OTHER_SALES_CHANNEL]));
        $this->storage->log($this->record('at-upper-bound', new \DateTimeImmutable('2026-08-01 00:00:00')));

        $all = $this->storage->iterate(new \DateTimeImmutable('2026-07-01'), new \DateTimeImmutable('2026-08-01'));
        static::assertSame(['in-range', 'other-channel'], $this->consentIds($all));

        $oneChannel = $this->storage->iterate(new \DateTimeImmutable('2026-07-01'), new \DateTimeImmutable('2026-08-01'), TestDefaults::SALES_CHANNEL);
        static::assertSame(['in-range'], $this->consentIds($oneChannel));
    }

    public function testIteratePagesThroughMoreThanOneBatchWithoutSkippingOrRepeating(): void
    {
        // Same timestamp for every row, so the id tie-breaker of the keyset pagination is exercised
        $createdAt = (new \DateTimeImmutable('2026-07-13 12:00:00'))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $rows = [];
        for ($i = 0; $i < 1001; ++$i) {
            $rows[] = \sprintf(
                '(%s, \'visitor-%04d\', \'accept_all\', \'banner\', \'{}\', \'[]\', \'hash\', %s, %s, \'%s\')',
                $this->connection->quote(Uuid::randomBytes()),
                $i,
                $this->connection->quote(Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL)),
                $this->connection->quote(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)),
                $createdAt,
            );
        }
        $this->connection->executeStatement(
            'INSERT INTO `cookie_consent_log` (`id`, `consent_id`, `consent_action`, `source`, `group_decisions`, `accepted_cookies`, `config_hash`, `sales_channel_id`, `language_id`, `created_at`) VALUES '
            . implode(', ', $rows),
        );

        $consentIds = $this->consentIds($this->storage->iterate(new \DateTimeImmutable('2026-07-13'), new \DateTimeImmutable('2026-07-14')));

        static::assertCount(1001, $consentIds);
        static::assertCount(1001, array_unique($consentIds));
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
     * @param array<string, mixed> $overrides
     */
    private function record(string $consentId, \DateTimeImmutable $createdAt, array $overrides = []): CookieConsentRecord
    {
        return new CookieConsentRecord(
            consentId: $consentId,
            consentAction: $overrides['consentAction'] ?? CookieConsentAction::ACCEPT_ALL,
            source: CookieConsentSource::BANNER,
            groupDecisions: $overrides['groupDecisions'] ?? ['cookie.groupRequired' => CookieConsentDecision::ACCEPTED],
            acceptedCookies: $overrides['acceptedCookies'] ?? [],
            configHash: 'hash',
            salesChannelId: $overrides['salesChannelId'] ?? TestDefaults::SALES_CHANNEL,
            languageId: Defaults::LANGUAGE_SYSTEM,
            createdAt: $createdAt,
        );
    }
}
