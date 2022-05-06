<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1642517958AddCascadeDeleteToTagRelations;

/**
 * @internal
 */
class Migration1642517958AddCascadeDeleteToTagRelationsTest extends TestCase
{
    use KernelTestBehaviour;

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

    private AbstractSchemaManager $schemaManager;

    private Migration1642517958AddCascadeDeleteToTagRelations $migration;

    public function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->schemaManager = $this->getContainer()->get(Connection::class)->getSchemaManager();
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

            if ($foreignKey->getForeignTableName() === 'tag' && $foreignKey->getColumns() === ['tag_id']) {
                return $foreignKey;
            }
        }

        return null;
    }
}
