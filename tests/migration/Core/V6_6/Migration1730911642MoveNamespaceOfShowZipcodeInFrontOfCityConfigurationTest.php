<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1730911642MoveNamespaceOfShowZipcodeInFrontOfCityConfiguration;

/**
 * @internal
 */
#[Package('core')]
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
        // Revert the change, already done by the migration itself
        $this->connection->update('system_config', [
            'configuration_key' => 'core.address.showZipcodeInFrontOfCity',
        ], [
            'configuration_key' => 'core.loginRegistration.showZipcodeInFrontOfCity',
        ]);

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('configuration_value')
            ->from('system_config')
            ->where(
                $qb->expr()->like('configuration_key', ':configKey')
            )
            ->setParameter('configKey', 'core.address.showZipcodeInFrontOfCity')
        ;

        $previousValue = $qb->executeQuery()->fetchOne();
        static::assertIsString($previousValue);

        $previousValue = json_decode($previousValue, true);

        static::assertArrayHasKey('_value', $previousValue);
        static::assertTrue($previousValue['_value']);

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
        static::assertSame($previousValue['_value'], $afterValue['_value']);

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
