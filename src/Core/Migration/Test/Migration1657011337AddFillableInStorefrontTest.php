<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1657011337AddFillableInStorefront;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\Migration1657011337AddFillableInStorefront
 */
class Migration1657011337AddFillableInStorefrontTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMultipleExecution(): void
    {
        $con = $this->getContainer()->get(Connection::class);

        $m = new Migration1657011337AddFillableInStorefront();
        $m->update($con);
        $m->update($con);

        static::assertTrue($this->columnExists($con));
    }

    public function testColumnGetsCreated(): void
    {
        $con = $this->getContainer()->get(Connection::class);

        $con->executeStatement('ALTER TABLE `custom_field` DROP `allow_customer_write`;');

        static::assertFalse($this->columnExists($con));

        $m = new Migration1657011337AddFillableInStorefront();
        $m->update($con);

        static::assertTrue($this->columnExists($con));
    }

    private function columnExists(Connection $connection): bool
    {
        $field = $connection->fetchColumn(
            'SHOW COLUMNS FROM `custom_field` WHERE `Field` LIKE :column;',
            ['column' => 'allow_customer_write']
        );

        return $field === 'allow_customer_write';
    }
}
