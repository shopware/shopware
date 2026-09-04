<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1787737629AddCookieConsentRetentionConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1787737629AddCookieConsentRetentionConfig::class)]
class Migration1787737629AddCookieConsentRetentionConfigTest extends TestCase
{
    private const CONFIG_KEY = 'core.cookieConsentRetention.days';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->deleteConfig();
    }

    protected function tearDown(): void
    {
        $this->deleteConfig();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1787737629AddCookieConsentRetentionConfig();
        static::assertSame(1787737629, $migration->getCreationTimestamp());
    }

    public function testMigrationSeedsTheDefaultRetention(): void
    {
        $migration = new Migration1787737629AddCookieConsentRetentionConfig();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame([120], $this->fetchValues());
    }

    public function testMigrationKeepsAnExistingValue(): void
    {
        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => '{"_value": 30}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        (new Migration1787737629AddCookieConsentRetentionConfig())->update($this->connection);

        static::assertSame([30], $this->fetchValues());
    }

    /**
     * The raw column is JSON, so assert on the decoded value rather than on the
     * exact string the database happens to normalise it to.
     *
     * @return list<mixed>
     */
    private function fetchValues(): array
    {
        return array_map(
            static fn (mixed $value) => json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR)['_value'] ?? null,
            $this->connection->fetchFirstColumn(
                'SELECT configuration_value FROM system_config WHERE configuration_key = :key',
                ['key' => self::CONFIG_KEY],
            ),
        );
    }

    private function deleteConfig(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM system_config WHERE configuration_key = :key',
            ['key' => self::CONFIG_KEY],
        );
    }
}
