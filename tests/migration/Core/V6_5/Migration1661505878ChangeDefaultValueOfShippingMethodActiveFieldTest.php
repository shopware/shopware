<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1661505878ChangeDefaultValueOfShippingMethodActiveField;

/**
 * @internal
 */
#[CoversClass(Migration1661505878ChangeDefaultValueOfShippingMethodActiveField::class)]
class Migration1661505878ChangeDefaultValueOfShippingMethodActiveFieldTest extends TestCase
{
    public function testUpdateActiveColumnDefaultValue(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $sql = 'ALTER TABLE shipping_method ALTER `active` SET DEFAULT 1;';
        $connection->executeStatement($sql);

        static::assertTrue($this->getDefaultValue($connection));

        $migration = new Migration1661505878ChangeDefaultValueOfShippingMethodActiveField();
        $migration->update($connection);
        $migration->update($connection);

        static::assertFalse($this->getDefaultValue($connection));
    }

    private function getDefaultValue(Connection $connection): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT DISTINCT DEFAULT(`active`) FROM `shipping_method`;'
        );
    }
}
