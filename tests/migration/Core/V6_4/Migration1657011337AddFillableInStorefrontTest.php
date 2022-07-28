<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1657011337AddFillableInStorefront;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1657011337AddFillableInStorefront
 */
class Migration1657011337AddFillableInStorefrontTest extends TestCase
{
    public function testMultipleExecution(): void
    {
        $con = KernelLifecycleManager::getConnection();

        $m = new Migration1657011337AddFillableInStorefront();
        $m->update($con);
        $m->update($con);

        static::assertTrue($this->columnExists($con));
    }

    public function testColumnGetsCreated(): void
    {
        $con = KernelLifecycleManager::getConnection();

        $con->executeStatement('ALTER TABLE `custom_field` DROP `allow_customer_write`;');

        static::assertFalse($this->columnExists($con));

        $m = new Migration1657011337AddFillableInStorefront();
        $m->update($con);

        static::assertTrue($this->columnExists($con));
    }

    private function columnExists(Connection $connection): bool
    {
        $field = $connection->fetchOne(
            'SHOW COLUMNS FROM `custom_field` WHERE `Field` LIKE :column;',
            ['column' => 'allow_customer_write']
        );

        return $field === 'allow_customer_write';
    }
}
