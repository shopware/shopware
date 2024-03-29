<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1663402842AddPathToMedia;

/**
 * @internal
 */
#[CoversClass(Migration1663402842AddPathToMedia::class)]
class Migration1663402842AddPathToMediaTest extends TestCase
{
    use KernelTestBehaviour;

    protected function setUp(): void
    {
        try {
            $this->getContainer()
                ->get(Connection::class)
                ->executeStatement('ALTER TABLE `media` DROP COLUMN `path`;');

            $this->getContainer()
                ->get(Connection::class)
                ->executeStatement('ALTER TABLE `media_thumbnail` DROP COLUMN `path`;');
        } catch (\Throwable) {
        }
    }

    public function testItAddsPathToMedia(): void
    {
        $migration = new Migration1663402842AddPathToMedia();

        $migration->update($this->getContainer()->get(Connection::class));

        static::assertTrue(
            $this->getContainer()
                ->get(Connection::class)
                ->fetchOne('SHOW COLUMNS FROM `media` LIKE \'path\';') !== false
        );

        static::assertTrue(
            $this->getContainer()
                ->get(Connection::class)
                ->fetchOne('SHOW COLUMNS FROM `media_thumbnail` LIKE \'path\';') !== false
        );

        // test duplicate execution
        $migration->update($this->getContainer()->get(Connection::class));
    }
}
