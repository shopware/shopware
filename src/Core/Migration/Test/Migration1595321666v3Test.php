<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1595321666v3;

class Migration1595321666v3Test extends TestCase
{
    use KernelTestBehaviour;

    public function testRemoveTriggerShouldNotThrowAnErrorWhenTriggerNotExists(): void
    {
        $conn = $this->getContainer()->get(Connection::class);

        $conn->executeUpdate('DROP TRIGGER IF EXISTS `shipping_method_price_new_price_update`');
        $conn->executeUpdate('DROP TRIGGER IF EXISTS `shipping_method_price_new_price_insert`');

        $actualTrigger = $conn->fetchAll('SHOW TRIGGERS LIKE "shipping_method_price"');

        static::assertCount(0, $actualTrigger);

        $migration = new Migration1595321666v3();
        $migration->removeTrigger($conn, 'shipping_method_price_new_price_update');
        $migration->removeTrigger($conn, 'shipping_method_price_new_price_insert');
    }
}
