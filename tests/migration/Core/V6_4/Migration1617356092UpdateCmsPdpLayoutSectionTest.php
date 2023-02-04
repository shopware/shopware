<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1609140710AddCmsPdpLayout;
use Shopware\Core\Migration\V6_4\Migration1611732852UpdateCmsPdpLayout;
use Shopware\Core\Migration\V6_4\Migration1617356092UpdateCmsPdpLayoutSection;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1617356092UpdateCmsPdpLayoutSection
 */
class Migration1617356092UpdateCmsPdpLayoutSectionTest extends TestCase
{
    use MigrationTestTrait;

    private const FK_CATEGORY_TABLE = 'category';
    private const FK_PRODUCT_TABLE = 'product';
    private const FK_CATEGORY_COLUMN = 'cms_page_id';
    private const FK_PRODUCT_COLUMN = 'cms_page_id';
    private const FK_CMS_PAGE_TABLE = 'cms_page';
    private const FK_CMS_PAGE_COLUMN = 'id';

    private const FK_CATEGORY_INDEX = 'fk.category.cms_page_id';
    private const FK_PRODUCT_INDEX = 'fk.product.cms_page_id';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationFields(): void
    {
        $this->connection->rollBack();
        $this->removeForeignKeyConstraintIfExists();
        $this->connection->beginTransaction();

        $this->rollbackMigrationChanges();

        $blocks = $this->fetchCmsBlocks();
        static::assertEmpty($blocks);

        $this->runMigration();

        $blocks = $this->fetchCmsBlocks();

        $expectedBlocks = [
            [
                'margin_top' => '0',
                'margin_bottom' => '20px',
                'margin_left' => '0',
                'margin_right' => '0',
                'type' => 'product-heading',
            ],
            [
                'margin_top' => '20px',
                'margin_bottom' => '0',
                'margin_left' => '0',
                'margin_right' => '0',
                'type' => 'gallery-buybox',
            ],
            [
                'margin_top' => '20px',
                'margin_bottom' => '20px',
                'margin_left' => '0',
                'margin_right' => '0',
                'type' => 'product-description-reviews',
            ],
            [
                'margin_top' => '0',
                'margin_bottom' => '0',
                'margin_left' => '0',
                'margin_right' => '0',
                'type' => 'cross-selling',
            ],
        ];

        foreach ($blocks as $block) {
            static::assertContainsEquals($block, $expectedBlocks);
        }

        $slotTranslations = $this->fetchSlotConfigs();

        $expectedSlotTranslations = [
            [
                'displayMode' => ['source' => 'static', 'value' => 'standard'],
                'media' => ['value' => 'product.manufacturer.media', 'source' => 'mapped'],
                'minHeight' => ['value' => null, 'source' => 'static'],
                'newTab' => ['value' => true, 'source' => 'static'],
                'url' => ['value' => null, 'source' => 'static'],
                'verticalAlign' => ['value' => null, 'source' => 'static'],
            ],
            [
                'displayMode' => ['value' => 'contain', 'source' => 'static'],
                'fullScreen' => ['value' => true, 'source' => 'static'],
                'galleryPosition' => ['value' => 'left', 'source' => 'static'],
                'minHeight' => ['value' => '430px', 'source' => 'static'],
                'navigationArrows' => ['value' => 'inside', 'source' => 'static'],
                'navigationDots' => ['value' => 'inside', 'source' => 'static'],
                'sliderItems' => ['value' => 'product.media', 'source' => 'mapped'],
                'verticalAlign' => ['value' => null, 'source' => 'static'],
                'zoom' => ['value' => true, 'source' => 'static'],
            ],
        ];

        foreach ($slotTranslations as $slotTranslation) {
            static::assertContainsEquals(json_decode($slotTranslation, true, 512, \JSON_THROW_ON_ERROR), $expectedSlotTranslations);
        }
    }

    private function runMigration(): void
    {
        $migration = new Migration1609140710AddCmsPdpLayout();
        $migration->update($this->connection);
        $migrationUpdate = new Migration1611732852UpdateCmsPdpLayout();
        $migrationUpdate->update($this->connection);
        $migrationUpdate = new Migration1617356092UpdateCmsPdpLayoutSection();
        $migrationUpdate->update($this->connection);
    }

    /**
     * @return array<string>
     */
    private function fetchSlotConfigs(): array
    {
        /** @var array<string> $slotIds */
        $slotIds = $this->connection->fetchFirstColumn('
            SELECT id
            FROM cms_slot
            WHERE type = "image-gallery" OR type = "manufacturer-logo"
        ');

        return $this->connection->fetchFirstColumn('
            SELECT config
            FROM cms_slot_translation
            WHERE cms_slot_id IN (:slotId)
        ', ['slotId' => $slotIds], ['slotId' => Connection::PARAM_STR_ARRAY]);
    }

    /**
     * @return array{margin_top: string, margin_bottom: string, margin_left: string, margin_right: string, type: string}[]
     */
    private function fetchCmsBlocks(): array
    {
        /** @var array{margin_top: string, margin_bottom: string, margin_left: string, margin_right: string, type: string}[] $result */
        $result = $this->connection->fetchAllAssociative('
            SELECT margin_top, margin_bottom, margin_left, margin_right, type
            FROM cms_block
        ');

        return $result;
    }

    private function rollbackMigrationChanges(): void
    {
        $this->connection->executeStatement('DELETE FROM `cms_page_translation`');
        $this->connection->executeStatement('DELETE FROM `cms_page`');
        $this->connection->executeStatement('DELETE FROM `cms_section`');
        $this->connection->executeStatement('DELETE FROM `cms_block`');
        $this->connection->executeStatement('DELETE FROM `cms_slot_translation`');
        $this->connection->executeStatement('DELETE FROM `cms_slot`');
    }

    private function removeForeignKeyConstraintIfExists(): void
    {
        $database = $this->connection->fetchOne('select database();');
        $categoryKeyName = $this->getForeignKeyName($database, self::FK_CATEGORY_TABLE, self::FK_CMS_PAGE_TABLE, self::FK_CATEGORY_COLUMN, self::FK_CMS_PAGE_COLUMN);
        $productKeyName = $this->getForeignKeyName($database, self::FK_PRODUCT_TABLE, self::FK_CMS_PAGE_TABLE, self::FK_PRODUCT_COLUMN, self::FK_CMS_PAGE_COLUMN);

        if ($categoryKeyName !== null) {
            $this->connection->executeStatement(self::dropIndexAndForeignKeyQuery(
                self::FK_CATEGORY_TABLE,
                self::FK_CATEGORY_INDEX,
                $categoryKeyName
            ));
        }

        if ($productKeyName !== null) {
            $this->connection->executeStatement(self::dropIndexAndForeignKeyQuery(
                self::FK_PRODUCT_TABLE,
                self::FK_PRODUCT_INDEX,
                $productKeyName
            ));
        }
    }

    private function getForeignKeyName(string $database, string $localTable, string $referenceTable, string $localColumn, string $referenceColumn): ?string
    {
        $foreignKeyName = $this->connection->fetchOne(self::getForeignKeyQuery($database, $localTable, $referenceTable, $localColumn, $referenceColumn));

        if (\is_string($foreignKeyName) && !empty($foreignKeyName)) {
            return $foreignKeyName;
        }

        return null;
    }

    private static function getForeignKeyQuery(string $database, string $localTable, string $referenceTable, string $localColumn, string $referenceColumn): string
    {
        $template = <<<'EOF'
SELECT `CONSTRAINT_NAME`
FROM `information_schema`.`KEY_COLUMN_USAGE`
WHERE
    `CONSTRAINT_SCHEMA` = '#constrain_schema#' AND
    `TABLE_NAME` = '#local_table#' AND
    `REFERENCED_TABLE_NAME` = '#referenced_table#' AND
    `COLUMN_NAME` = '#local_column#' AND
    `REFERENCED_COLUMN_NAME` = '#referenced_column#';
EOF;

        return str_replace(
            ['#constrain_schema#', '#local_table#', '#referenced_table#', '#local_column#', '#referenced_column#'],
            [
                $database,
                $localTable,
                $referenceTable,
                $localColumn,
                $referenceColumn,
            ],
            $template
        );
    }

    /**
     * The foreign key name (in contrast to the index name) is not explicitly set in the tested migration,
     * therefore it needs to be determined at runtime.
     */
    private static function dropIndexAndForeignKeyQuery(string $table, string $index, string $foreignKey): string
    {
        $template = <<<'EOF'
ALTER TABLE `#table#` DROP FOREIGN KEY `#key#`;
DROP INDEX `#index#` ON `#table#`;
EOF;

        return str_replace(
            ['#table#', '#index#', '#key#'],
            [
                $table,
                $index,
                $foreignKey,
            ],
            $template
        );
    }
}
