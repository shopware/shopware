<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1595321666v3;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1595321666v3
 */
class Migration1595321666v3Test extends TestCase
{
    public function testRemoveTriggerShouldNotThrowAnErrorWhenTriggerNotExists(): void
    {
        $conn = KernelLifecycleManager::getConnection();

        $conn->executeStatement('DROP TRIGGER IF EXISTS `shipping_method_price_new_price_update`');
        $conn->executeStatement('DROP TRIGGER IF EXISTS `shipping_method_price_new_price_insert`');

        $actualTrigger = $conn->fetchAllAssociative('SHOW TRIGGERS LIKE "shipping_method_price"');

        static::assertCount(0, $actualTrigger);

        $migration = new Migration1595321666v3();
        $migration->removeTrigger($conn, 'shipping_method_price_new_price_update');
        $migration->removeTrigger($conn, 'shipping_method_price_new_price_insert');
    }
}
