<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1776827954AddExactSubfieldFlagToProductSearchConfigField;

/**
 * @internal
 */
#[CoversClass(Migration1776827954AddExactSubfieldFlagToProductSearchConfigField::class)]
class Migration1776827954AddExactSubfieldFlagToProductSearchConfigFieldTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $this->rollback();

        $migration = new Migration1776827954AddExactSubfieldFlagToProductSearchConfigField();
        static::assertSame(1776827954, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product_search_config_field', 'use_exact_subfield'));

        $column = TableHelper::getColumnOfTable($this->connection, 'product_search_config_field', 'use_exact_subfield');
        static::assertSame('boolean', $column->type);
        static::assertTrue($column->isNotNull);
        static::assertSame('0', (string) $column->defaultValue);

        $expectedFields = ['name', 'customSearchKeywords', 'productNumber', 'ean', 'manufacturerNumber'];

        $enabled = $this->connection->fetchAllKeyValue('
            SELECT `field`, `use_exact_subfield`
            FROM `product_search_config_field`
            WHERE `field` IN (:fields)
        ', ['fields' => $expectedFields], ['fields' => ArrayParameterType::STRING]);

        foreach ($enabled as $field => $value) {
            static::assertSame(1, (int) $value, \sprintf('use_exact_subfield should be enabled for "%s"', $field));
        }
    }

    private function rollback(): void
    {
        if (TableHelper::columnExists($this->connection, 'product_search_config_field', 'use_exact_subfield')) {
            $this->connection->executeStatement('ALTER TABLE `product_search_config_field` DROP COLUMN `use_exact_subfield`');
        }
    }
}
