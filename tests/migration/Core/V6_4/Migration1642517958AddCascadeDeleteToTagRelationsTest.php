<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1642517958AddCascadeDeleteToTagRelations;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1642517958AddCascadeDeleteToTagRelations
 */
class Migration1642517958AddCascadeDeleteToTagRelationsTest extends TestCase
{
    private const RELATION_TABLES = [
        'product_tag',
        'order_tag',
        'category_tag',
        'customer_tag',
        'landing_page_tag',
        'media_tag',
        'newsletter_recipient_tag',
        'shipping_method_tag',
    ];

    private Connection $connection;

    /**
     * @var AbstractSchemaManager<MySQLPlatform>
     */
    private AbstractSchemaManager $schemaManager;

    private Migration1642517958AddCascadeDeleteToTagRelations $migration;

    public function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->schemaManager = KernelLifecycleManager::getConnection()->createSchemaManager();
        $this->migration = new Migration1642517958AddCascadeDeleteToTagRelations();
    }

    public function testUpdate(): void
    {
        $this->migration->update($this->connection);

        foreach (self::RELATION_TABLES as $relationTable) {
            $foreignKeyName = $this->getForeignKey($relationTable);

            static::assertNotNull($foreignKeyName);
            static::assertEquals('CASCADE', $foreignKeyName->onDelete());
            static::assertEquals('CASCADE', $foreignKeyName->onUpdate());
        }
    }

    private function getForeignKey(string $relationTable): ?ForeignKeyConstraint
    {
        $foreignKeys = $this->schemaManager->listTableForeignKeys($relationTable);

        foreach ($foreignKeys as $foreignKey) {
            if (!$foreignKey instanceof ForeignKeyConstraint) {
                continue;
            }

            if ($foreignKey->getForeignTableName() === 'tag' && $foreignKey->getLocalColumns() === ['tag_id']) {
                return $foreignKey;
            }
        }

        return null;
    }
}
