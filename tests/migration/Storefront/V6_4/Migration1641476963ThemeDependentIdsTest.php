<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Storefront\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Storefront\Migration\V6_4\Migration1641476963ThemeDependentIds;

/**
 * @internal
 * @covers \Shopware\Storefront\Migration\V6_4\Migration1641476963ThemeDependentIds
 */
class Migration1641476963ThemeDependentIdsTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdate(): void
    {
        static::markTestSkipped('Flaky coverage');

        $migration = new Migration1641476963ThemeDependentIds();
        $migration->update($this->connection);

        $result = $this->connection->fetchAllAssociative('SELECT * FROM `theme_child`');

        static::assertIsArray($result);

        // second run will not fail
        $migration->update($this->connection);
    }
}
