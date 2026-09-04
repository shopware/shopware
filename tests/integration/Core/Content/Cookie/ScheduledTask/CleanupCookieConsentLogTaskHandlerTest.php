<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Cookie\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTaskHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
class CleanupCookieConsentLogTaskHandlerTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private CleanupCookieConsentLogTaskHandler $handler;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);

        $this->handler = new CleanupCookieConsentLogTaskHandler(
            static::getContainer()->get('scheduled_task.repository'),
            new NullLogger(),
            static::getContainer()->get(SystemConfigService::class),
            $this->connection,
            new NativeClock(),
        );
    }

    public function testRunDeletesExpiredLogsAndOrphanedConfigVersions(): void
    {
        $expiredDate = new \DateTimeImmutable('-150 days');
        $recentDate = new \DateTimeImmutable('-1 day');

        $this->insertConfigVersion('expired-hash', $expiredDate);
        $this->insertConfigVersion('current-hash', $expiredDate);
        $this->insertLog('expired-hash', $expiredDate);
        $this->insertLog('current-hash', $recentDate);

        $this->handler->run();

        static::assertSame(['current-hash'], $this->fetchLogHashes());
        static::assertSame(['current-hash'], $this->fetchConfigVersionHashes());
    }

    public function testRunKeepsRecentUnreferencedConfigVersions(): void
    {
        // A snapshot without log entries within the retention period must survive,
        // it may belong to a consent that is not committed yet
        $this->insertConfigVersion('fresh-hash', new \DateTimeImmutable('-1 day'));

        $this->handler->run();

        static::assertSame(['fresh-hash'], $this->fetchConfigVersionHashes());
    }

    public function testRunWithZeroRetentionDeletesEverythingExpired(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set(CleanupCookieConsentLogTaskHandler::CONFIG_KEY_RETENTION_DAYS, 0);

        $expiredDate = new \DateTimeImmutable('-1 hour');
        $this->insertConfigVersion('expired-hash', $expiredDate);
        $this->insertLog('expired-hash', $expiredDate);

        $this->handler->run();

        static::assertSame([], $this->fetchLogHashes());
    }

    public function testRunDoesNothingWhenRetentionIsDisabled(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set(CleanupCookieConsentLogTaskHandler::CONFIG_KEY_RETENTION_DAYS, -1);

        $expiredDate = new \DateTimeImmutable('-500 days');
        $this->insertConfigVersion('expired-hash', $expiredDate);
        $this->insertLog('expired-hash', $expiredDate);

        $this->handler->run();

        static::assertSame(['expired-hash'], $this->fetchLogHashes());
        static::assertSame(['expired-hash'], $this->fetchConfigVersionHashes());
    }

    private function insertLog(string $configHash, \DateTimeImmutable $createdAt): void
    {
        $this->connection->insert('cookie_consent_log', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'consent_action' => 'accept_all',
            'group_decisions' => '{"cookie.groupRequired":"accepted"}',
            'accepted_cookies' => '[]',
            'server_config_hash' => $configHash,
            'created_at' => $createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function insertConfigVersion(string $configHash, \DateTimeImmutable $createdAt): void
    {
        $this->connection->insert('cookie_consent_config_version', [
            'id' => Uuid::randomBytes(),
            'config_hash' => $configHash,
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'cookie_groups' => '[]',
            'created_at' => $createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @return list<string>
     */
    private function fetchLogHashes(): array
    {
        return $this->connection->fetchFirstColumn('SELECT `server_config_hash` FROM `cookie_consent_log` ORDER BY `server_config_hash`');
    }

    /**
     * @return list<string>
     */
    private function fetchConfigVersionHashes(): array
    {
        return $this->connection->fetchFirstColumn('SELECT `config_hash` FROM `cookie_consent_config_version` ORDER BY `config_hash`');
    }
}
