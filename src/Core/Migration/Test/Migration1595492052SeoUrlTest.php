<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1595492052SeoUrl;

class Migration1595492052SeoUrlTest extends TestCase
{
    use KernelTestBehaviour;

    public function testNoChanges(): void
    {
        /** @var Connection $conn */
        $conn = $this->getContainer()->get(Connection::class);
        $expectedSchema = $conn->fetchAssoc('SHOW CREATE TABLE `seo_url`')['Create Table'];

        $migration = new Migration1595492052SeoUrl();

        $migration->update($conn);
        $actualSchema = $conn->fetchAssoc('SHOW CREATE TABLE `seo_url`')['Create Table'];
        static::assertSame($expectedSchema, $actualSchema, 'Schema changed!. Run init again to have clean state');

        $migration->updateDestructive($conn);
        $actualSchema = $conn->fetchAssoc('SHOW CREATE TABLE `seo_url`')['Create Table'];
        static::assertSame($expectedSchema, $actualSchema, 'Schema changed!. Run init again to have clean state');
    }

    public function testNoSeoUrlTable(): void
    {
        /** @var Connection $conn */
        $conn = $this->getContainer()->get(Connection::class);
        $conn->executeUpdate('DROP TABLE `seo_url`');

        $migration = new Migration1595492052SeoUrl();
        $migration->update($conn);
        $exists = $conn->fetchColumn('SELECT COUNT(*) FROM `seo_url`') !== false;

        static::assertTrue($exists);
    }
}
