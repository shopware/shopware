<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1661505878ChangeDefaultValueOfShippingMethodActiveField;

/**
 * @internal
 * @covers \Shopware\Core\Migration\V6_5\Migration1661505878ChangeDefaultValueOfShippingMethodActiveField
 */
class Migration1661505878ChangeDefaultValueOfShippingMethodActiveFieldTest extends TestCase
{
    use KernelTestBehaviour;

    public function testUpdateActiveColumnDefaultValue(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

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
