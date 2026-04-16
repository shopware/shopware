<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1776384001AddCategoryTranslationLinkMediaId;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1776384001AddCategoryTranslationLinkMediaId::class)]
class Migration1776384001AddCategoryTranslationLinkMediaIdTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMigration(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $this->revertMigration($connection);

        $migration = new Migration1776384001AddCategoryTranslationLinkMediaId();
        $migration->update($connection);
        $migration->update($connection);

        $column = TableHelper::getColumnOfTable($connection, 'category_translation', 'link_media_id');
        static::assertFalse($column->isNotNull);

        $foreignKey = TableHelper::getForeignKeyOfTable(
            $connection,
            'category_translation',
            'fk.category_translation.link_media_id'
        );
        static::assertSame(['link_media_id'], $foreignKey->referencingColumnNames);
        static::assertSame('media', $foreignKey->referencedTableName);
        static::assertSame(['id'], $foreignKey->referencedColumnNames);
        static::assertSame('SET NULL', $foreignKey->onDeleteAction);
    }

    private function revertMigration(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `category_translation`'
            . ' DROP FOREIGN KEY `fk.category_translation.link_media_id`,'
            . ' DROP COLUMN `link_media_id`'
        );
    }
}
