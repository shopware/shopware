<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1595919251MainCategory;

class Migration1595919251MainCategoryTest extends TestCase
{
    use KernelTestBehaviour;

    public function testNoChanges(): void
    {
        /** @var Connection $conn */
        $conn = $this->getContainer()->get(Connection::class);
        $expectedSchema = $conn->fetchAssoc('SHOW CREATE TABLE `main_category`')['Create Table'];

        $migration = new Migration1595919251MainCategory();

        $migration->update($conn);
        $actualSchema = $conn->fetchAssoc('SHOW CREATE TABLE `main_category`')['Create Table'];
        static::assertSame($expectedSchema, $actualSchema, 'Schema changed!. Run init again to have clean state');

        $migration->updateDestructive($conn);
        $actualSchema = $conn->fetchAssoc('SHOW CREATE TABLE `main_category`')['Create Table'];
        static::assertSame($expectedSchema, $actualSchema, 'Schema changed!. Run init again to have clean state');
    }

    public function testNoSeoUrlTable(): void
    {
        /** @var Connection $conn */
        $conn = $this->getContainer()->get(Connection::class);
        $conn->executeUpdate('DROP TABLE `main_category`');

        $migration = new Migration1595919251MainCategory();
        $migration->update($conn);
        $exists = $conn->fetchColumn('SELECT COUNT(*) FROM `main_category`') !== false;

        static::assertTrue($exists);
    }
}
