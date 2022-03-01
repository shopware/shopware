<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Migration\V6_4\Migration1641476963ThemeDependentIds;

class Migration1641476963ThemeDependentIdsTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testUpdate(): void
    {
        $migration = new Migration1641476963ThemeDependentIds();
        $migration->update($this->connection);

        $result = $this->connection->fetchAllAssociative('SELECT * FROM `theme_child`');

        static::assertIsArray($result);

        // second run will not fail
        $migration->update($this->connection);
    }
}
