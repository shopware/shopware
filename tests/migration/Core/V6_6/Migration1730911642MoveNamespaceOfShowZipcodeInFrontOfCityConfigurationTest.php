<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1730911642MoveNamespaceOfShowZipcodeInFrontOfCityConfiguration;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1730911642MoveNamespaceOfShowZipcodeInFrontOfCityConfiguration::class)]
class Migration1730911642MoveNamespaceOfShowZipcodeInFrontOfCityConfigurationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1730911642MoveNamespaceOfShowZipcodeInFrontOfCityConfiguration();
        static::assertSame(1730911642, $migration->getCreationTimestamp());
    }

    public function testConfigExists(): void
    {
        // Revert the change already done by the migrations
        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.loginRegistration.showZipcodeInFrontOfCity',
            'configuration_value' => json_encode(['_value' => true]),
            'sales_channel_id' => null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $migration = new Migration1730911642MoveNamespaceOfShowZipcodeInFrontOfCityConfiguration();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('configuration_value')
            ->from('system_config')
            ->where(
                $qb->expr()->like('configuration_key', ':configKey')
            )
            ->setParameter('configKey', 'core.loginRegistration.showZipcodeInFrontOfCity')
        ;

        $afterValue = $qb->executeQuery()->fetchOne();
        static::assertIsString($afterValue);

        $afterValue = json_decode($afterValue, true);

        static::assertArrayHasKey('_value', $afterValue);
        static::assertTrue($afterValue['_value']);

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('COUNT(id)')
            ->from('system_config')
            ->where(
                $qb->expr()->like('configuration_key', ':configKey')
            )
            ->setParameter('configKey', 'core.address.showZipcodeInFrontOfCity')
        ;

        $originalSettingCount = (int) $qb->executeQuery()->fetchOne();
        static::assertSame(0, $originalSettingCount);
    }
}
