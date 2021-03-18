<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Migration1609140710AddCmsPdpLayout;

class Migration1609140710AddCmsPdpLayoutTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FK_CATEGORY_TABLE = 'category';
    private const FK_PRODUCT_TABLE = 'product';
    private const FK_CATEGORY_COLUMN = 'cms_page_id';
    private const FK_PRODUCT_COLUMN = 'cms_page_id';
    private const FK_CMS_PAGE_TABLE = 'cms_page';
    private const FK_CMS_PAGE_COLUMN = 'id';

    private const FK_CATEGORY_INDEX = 'fk.category.cms_page_id';
    private const FK_PRODUCT_INDEX = 'fk.product.cms_page_id';

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMigrationFields(): void
    {
        $this->connection->rollBack();
        $this->removeForeignKeyConstraintIfExists();
        $this->connection->beginTransaction();

        $this->rollbackMigrationChanges();

        $page = $this->fetchCmsPage();
        $pageTranslation = $this->fetchCmsPageTranslation();
        $section = $this->fetchCmsSection();
        $block = $this->fetchCmsBlock();
        $slot = $this->fetchCmsSlot();
        $slotTranslation = $this->fetchCmsSlotTranslation();
        static::assertEmpty($page);
        static::assertEmpty($pageTranslation);
        static::assertEmpty($section);
        static::assertEmpty($block);
        static::assertEmpty($slot);
        static::assertEmpty($slotTranslation);

        $this->runMigration();

        $page = $this->fetchCmsPage();
        $pageTranslations = $this->fetchCmsPageTranslation();
        $section = $this->fetchCmsSection();
        $blocks = $this->fetchCmsBlock();
        $slots = $this->fetchCmsSlot();
        $slotTranslations = $this->fetchCmsSlotTranslation();

        $expectedPage = [
            'type' => 'product_detail',
            'locked' => 1,
        ];
        static::assertContainsEquals($expectedPage, $page);

        $expectedPageTranslation = [
            [
                'name' => 'Standard Produktseite-Layout',
            ],
            [
                'name' => 'Default product page Layout',
            ],
        ];
        foreach ($pageTranslations as $pageTranslation) {
            static::assertContainsEquals($pageTranslation, $expectedPageTranslation);
        }

        $expectedSection = [
            'position' => 0,
            'type' => 'default',
        ];
        static::assertContainsEquals($expectedSection, $section);

        $expectedBlocks = [
            [
                'locked' => 1,
                'position' => 0,
                'type' => 'product-heading',
                'name' => 'Product heading',
                'margin_top' => '20px',
                'margin_bottom' => '20px',
                'margin_left' => '20px',
                'margin_right' => '20px',
                'background_media_mode' => 'cover',
            ],
            [
                'locked' => 1,
                'position' => 1,
                'type' => 'gallery-buybox',
                'name' => 'Gallery buy box',
                'margin_top' => '20px',
                'margin_bottom' => '20px',
                'margin_left' => '20px',
                'margin_right' => '20px',
                'background_media_mode' => 'cover',
            ],
            [
                'locked' => 1,
                'position' => 2,
                'type' => 'product-description-reviews',
                'name' => 'Product description and reviews',
                'margin_top' => '20px',
                'margin_bottom' => '20px',
                'margin_left' => '20px',
                'margin_right' => '20px',
                'background_media_mode' => 'cover',
            ],
            [
                'locked' => 1,
                'position' => 3,
                'type' => 'cross-selling',
                'name' => 'Cross selling',
                'margin_top' => '20px',
                'margin_bottom' => '20px',
                'margin_left' => '20px',
                'margin_right' => '20px',
                'background_media_mode' => 'cover',
            ],
        ];
        foreach ($blocks as $block) {
            static::assertContainsEquals($block, $expectedBlocks);
        }

        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $expectedSlots = [
            [
                'locked' => 1,
                'type' => 'product-name',
                'slot' => 'left',
                'version_id' => $versionId,
            ],
            [
                'locked' => 1,
                'type' => 'manufacturer-logo',
                'slot' => 'right',
                'version_id' => $versionId,
            ],
            [
                'locked' => 1,
                'type' => 'image-gallery',
                'slot' => 'left',
                'version_id' => $versionId,
            ],
            [
                'locked' => 1,
                'type' => 'buy-box',
                'slot' => 'right',
                'version_id' => $versionId,
            ],
            [
                'locked' => 1,
                'type' => 'product-description-reviews',
                'slot' => 'content',
                'version_id' => $versionId,
            ],
            [
                'locked' => 1,
                'type' => 'cross-selling',
                'slot' => 'content',
                'version_id' => $versionId,
            ],
        ];
        foreach ($slots as $slot) {
            static::assertContainsEquals($slot, $expectedSlots);
        }

        $expectedSlotTranslations = [
            [
                'product' => ['value' => null, 'source' => 'static'],
                'boxLayout' => ['source' => 'static', 'value' => 'standard'],
                'elMinWidth' => ['value' => '200px', 'source' => 'static'],
                'displayMode' => ['source' => 'static', 'value' => 'standard'],
            ],
            [
                'product' => ['value' => null, 'source' => 'static'],
                'alignment' => ['value' => null, 'source' => 'static'],
            ],
            [
                'zoom' => ['value' => false, 'source' => 'static'],
                'minHeight' => ['value' => '340px', 'source' => 'static'],
                'fullScreen' => ['value' => false, 'source' => 'static'],
                'displayMode' => ['value' => 'standard', 'source' => 'static'],
                'sliderItems' => ['value' => 'product.media', 'source' => 'mapped'],
                'verticalAlign' => ['value' => null, 'source' => 'static'],
                'navigationDots' => ['value' => null, 'source' => 'static'],
                'galleryPosition' => ['value' => 'left', 'source' => 'static'],
                'navigationArrows' => ['value' => 'inside', 'source' => 'static'],
            ],
            [
                'product' => ['value' => null, 'source' => 'static'],
                'alignment' => ['value' => null, 'source' => 'static'],
            ],
            [
                'content' => ['source' => 'mapped', 'value' => 'product.name'],
                'verticalAlign' => ['value' => null, 'source' => 'static'],
            ],
            [
                'url' => ['source' => 'static', 'value' => null],
                'media' => ['source' => 'mapped', 'value' => 'product.manufacturer.media'],
                'newTab' => ['source' => 'static', 'value' => true],
                'minHeight' => ['source' => 'static', 'value' => null],
                'displayMode' => ['source' => 'static', 'value' => 'cover'],
                'verticalAlign' => ['source' => 'static', 'value' => null],
            ],
        ];

        foreach ($slotTranslations as $slotTranslation) {
            static::assertContainsEquals(json_decode($slotTranslation['config'], true), $expectedSlotTranslations);
        }
    }

    private function runMigration(): void
    {
        $migration = new Migration1609140710AddCmsPdpLayout();
        $migration->update($this->connection);
    }

    private function fetchCmsPage(): array
    {
        return $this->connection->fetchAll('
            SELECT type, locked
            FROM cms_page
            ORDER BY created_at
        ');
    }

    private function fetchCmsPageTranslation(): array
    {
        return $this->connection->fetchAll('
            SELECT name
            FROM cms_page_translation
            ORDER BY created_at
        ');
    }

    private function fetchCmsSection(): array
    {
        return $this->connection->fetchAll('
            SELECT position, type
            FROM cms_section
            ORDER BY created_at
        ');
    }

    private function fetchCmsBlock(): array
    {
        return $this->connection->fetchAll('
            SELECT locked, position, type, name, margin_top, margin_bottom, margin_left, margin_right, background_media_mode
            FROM cms_block
            ORDER BY created_at
        ');
    }

    private function fetchCmsSlot(): array
    {
        return $this->connection->fetchAll('
            SELECT locked, type, slot, version_id
            FROM cms_slot
            ORDER BY created_at
        ');
    }

    private function fetchCmsSlotTranslation(): array
    {
        return $this->connection->fetchAll('
            SELECT config
            FROM cms_slot_translation
            ORDER BY created_at
        ');
    }

    private function rollbackMigrationChanges(): void
    {
        $this->connection->executeUpdate('DELETE FROM `cms_page_translation`');
        $this->connection->executeUpdate('DELETE FROM `cms_page`');
        $this->connection->executeUpdate('DELETE FROM `cms_section`');
        $this->connection->executeUpdate('DELETE FROM `cms_block`');
        $this->connection->executeUpdate('DELETE FROM `cms_slot_translation`');
        $this->connection->executeUpdate('DELETE FROM `cms_slot`');
    }

    private function removeForeignKeyConstraintIfExists(): void
    {
        $database = $this->connection->fetchColumn('select database();');
        $categoryKeyName = $this->getForeignKeyName($database, self::FK_CATEGORY_TABLE, self::FK_CMS_PAGE_TABLE, self::FK_CATEGORY_COLUMN, self::FK_CMS_PAGE_COLUMN);
        $productKeyName = $this->getForeignKeyName($database, self::FK_PRODUCT_TABLE, self::FK_CMS_PAGE_TABLE, self::FK_PRODUCT_COLUMN, self::FK_CMS_PAGE_COLUMN);

        if ($categoryKeyName !== null) {
            $this->connection->executeUpdate(self::dropIndexAndForeignKeyQuery(
                self::FK_CATEGORY_TABLE,
                self::FK_CATEGORY_INDEX,
                $categoryKeyName
            ));
        }

        if ($productKeyName !== null) {
            $this->connection->executeUpdate(self::dropIndexAndForeignKeyQuery(
                self::FK_PRODUCT_TABLE,
                self::FK_PRODUCT_INDEX,
                $productKeyName
            ));
        }
    }

    private function getForeignKeyName(string $database, string $localTable, string $referenceTable, string $localColumn, string $referenceColumn): ?string
    {
        $foreignKeyName = $this->connection->fetchColumn(self::getForeignKeyQuery($database, $localTable, $referenceTable, $localColumn, $referenceColumn));

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
