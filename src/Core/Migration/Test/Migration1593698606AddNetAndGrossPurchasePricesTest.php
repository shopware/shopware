<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1595919251MainCategory;

class Migration1593698606AddNetAndGrossPurchasePricesTest extends TestCase
{
    use KernelTestBehaviour;

    public function testNoChanges(): void
    {
        /** @var Connection $conn */
        $conn = $this->getContainer()->get(Connection::class);
        $expectedProductSchema = $conn->fetchAssoc('SHOW CREATE TABLE `product`')['Create Table'];

        $migration = new Migration1595919251MainCategory();

        $migration->update($conn);
        $actualProductSchema = $conn->fetchAssoc('SHOW CREATE TABLE `product`')['Create Table'];
        static::assertSame($expectedProductSchema, $actualProductSchema, 'Schema changed!. Run init again to have clean state');
    }

    public function testTriggersSet(): void
    {
        if ($_SERVER['BLUE_GREEN_DEPLOYMENT'] === '0' || $_SERVER['BLUE_GREEN_DEPLOYMENT'] === false) {
            static::markTestSkipped('BLUE_GREEN_DEPLOYMENT is false, so no triggers writeable');
        }
        $databaseName = substr(parse_url($_SERVER['DATABASE_URL'])['path'], 1);
        /** @var Connection $conn */
        $conn = $this->getContainer()->get(Connection::class);
        $updateTrigger = $conn->fetchAll('SHOW TRIGGERS IN ' . $databaseName . ' WHERE `Trigger` = \'product_purchase_prices_update\'');

        static::assertCount(1, $updateTrigger);

        $insertTrigger = $conn->fetchAll('SHOW TRIGGERS IN ' . $databaseName . ' WHERE `Trigger` = \'product_purchase_prices_insert\'');

        static::assertCount(1, $insertTrigger);
    }
}
