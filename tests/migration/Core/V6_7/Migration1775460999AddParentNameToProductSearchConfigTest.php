<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1775460999AddParentNameToProductSearchConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1775460999AddParentNameToProductSearchConfig::class)]
class Migration1775460999AddParentNameToProductSearchConfigTest extends TestCase
{
    private Connection $connection;

    private Migration1775460999AddParentNameToProductSearchConfig $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1775460999AddParentNameToProductSearchConfig();

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1775460999, $this->migration->getCreationTimestamp());
    }

    public function testAddParentNameToProductSearchConfig(): void
    {
        $nameConfig = $this->connection->fetchAssociative(
            'SELECT product_search_config_id, tokenize, searchable, ranking
            FROM product_search_config_field
            WHERE field = :field
            LIMIT 1',
            ['field' => 'name']
        );

        if ($nameConfig === false) {
            static::markTestSkipped('No product search config field for name found');
        }

        $this->connection->executeStatement(
            'DELETE FROM product_search_config_field WHERE product_search_config_id = :configId AND field = :field',
            [
                'configId' => $nameConfig['product_search_config_id'],
                'field' => 'parent.name',
            ]
        );

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $parentConfig = $this->connection->fetchAssociative(
            'SELECT tokenize, searchable, ranking
            FROM product_search_config_field
            WHERE product_search_config_id = :configId AND field = :field',
            [
                'configId' => $nameConfig['product_search_config_id'],
                'field' => 'parent.name',
            ]
        );

        static::assertNotFalse($parentConfig);
        static::assertSame((string) $nameConfig['tokenize'], (string) $parentConfig['tokenize']);
        static::assertSame('0', (string) $parentConfig['searchable']);
        static::assertSame((string) round((float) $nameConfig['ranking'] * 0.8), (string) $parentConfig['ranking']);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM product_search_config_field WHERE product_search_config_id = :configId AND field = :field',
            [
                'configId' => $nameConfig['product_search_config_id'],
                'field' => 'parent.name',
            ]
        );

        static::assertSame('1', (string) $count);
    }
}
