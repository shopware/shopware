<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1654839361ProductDownloadMedia;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1654839361ProductDownloadMedia
 */
class Migration1654839361ProductDownloadMediaTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->prepare();
    }

    public function testMigration(): void
    {
        $migration = new Migration1654839361ProductDownloadMedia();
        $migration->update($this->connection);
        // test it can be executed multiple times
        $migration->update($this->connection);

        static::assertNotFalse($this->connection->fetchOne('SELECT 1 FROM `media_default_folder` WHERE `entity` = "product_download"'));
        static::assertNotFalse($this->connection->fetchOne('SELECT 1 FROM `media_folder` WHERE `name` = "Product downloads"'));
    }

    private function prepare(): void
    {
        $this->connection->executeStatement('DELETE FROM `media_default_folder` WHERE `entity` = "product_download"');
        $this->connection->executeStatement('DELETE FROM `media_folder` WHERE `name` = "Product downloads"');
    }
}
