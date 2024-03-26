<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1711418838ReplaceSortingOptionKeysWithSortingOptionIdsInCmsSlots;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1711418838ReplaceSortingOptionKeysWithSortingOptionIdsInCmsSlots::class)]
class Migration1711418838ReplaceSortingOptionKeysWithSortingOptionIdsInCmsSlotsTest extends TestCase
{
    use MigrationTestTrait;

    private Migration1711418838ReplaceSortingOptionKeysWithSortingOptionIdsInCmsSlots $migration;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->migration = new Migration1711418838ReplaceSortingOptionKeysWithSortingOptionIdsInCmsSlots();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $sortingIds = $this->getSortingIds();
        $this->addProductListingCmsSlot($this->connection);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $slots = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT cms_slot_id, cms_slot_version_id, language_id, config
            FROM cms_slot_translation
            WHERE JSON_CONTAINS_PATH(config, 'one', '$.defaultSorting')
                OR JSON_CONTAINS_PATH(config, 'one', '$.availableSortings');
        SQL);

        // First slot
        $slotConfig = json_decode($slots[0]['config'], true);

        static::assertSame(
            $sortingIds['price-desc'],
            Uuid::fromHexToBytes($slotConfig['defaultSorting']['value'])
        );

        $availableSortings = $slotConfig['availableSortings']['value'];

        // filter out the sorting key 'random-example-sorting'
        static::assertCount(3, $availableSortings);
        static::assertFalse(\in_array('random-example-sorting', array_keys($availableSortings), true));

        static::assertSame(
            1,
            $availableSortings[Uuid::fromBytesToHex($sortingIds['price-desc'])]
        );
        static::assertSame(
            2,
            $availableSortings[Uuid::fromBytesToHex($sortingIds['price-asc'])]
        );
        static::assertSame(
            3,
            $availableSortings[Uuid::fromBytesToHex($sortingIds['name-asc'])]
        );

        // Second slot
        $secondSlotConfig = json_decode($slots[1]['config'], true);
        static::assertSame(
            $sortingIds['name-asc'],
            Uuid::fromHexToBytes($secondSlotConfig['defaultSorting']['value'])
        );
    }

    private function addProductListingCmsSlot(Connection $connection): void
    {
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        // cms page
        $page = [
            'id' => Uuid::randomBytes(),
            'type' => 'product_list',
            'locked' => 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
        $pageEng = [
            'cms_page_id' => $page['id'],
            'language_id' => $languageEn,
            'name' => 'Listing Page EN',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $connection->insert('cms_page', $page);
        $connection->insert('cms_page_translation', $pageEng);

        $section = [
            'id' => Uuid::randomBytes(),
            'cms_page_id' => $page['id'],
            'position' => 0,
            'type' => 'default',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $connection->insert('cms_section', $section);

        $sortingIds = $this->getSortingIds();

        // First CMS block & slot
        $availableSortings = [
            'price-desc' => 1,
            'price-asc' => 2,
            Uuid::fromBytesToHex($sortingIds['name-asc']) => 3,
            'random-example-sorting' => 4,
        ];
        $blockId = $this->createCmsBlock($connection, $section['id']);
        $this->createCmsSlot($connection, $blockId, 'price-desc', $availableSortings);

        // Second CMS block & slot
        $secondBlockId = $this->createCmsBlock($connection, $section['id']);
        $this->createCmsSlot($connection, $secondBlockId, Uuid::fromBytesToHex($sortingIds['name-asc']), $availableSortings);
    }

    /**
     * @return string[]
     */
    private function getSortingIds(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT url_key, id FROM product_sorting');
    }

    private function createCmsBlock(Connection $connection, string $sectionId): string
    {
        $id = Uuid::randomBytes();

        $connection->insert('cms_block', [
            'id' => $id,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'cms_section_id' => $sectionId,
            'locked' => 1,
            'position' => 1,
            'type' => 'product-listing',
            'name' => 'Category listing',
            'margin_top' => '20px',
            'margin_bottom' => '20px',
            'margin_left' => '20px',
            'margin_right' => '20px',
            'background_media_mode' => 'cover',
        ]);

        return $id;
    }

    /**
     * @param array<string, int> $availableSortings
     */
    private function createCmsSlot(Connection $connection, string $blockId, string $defaulSorting, array $availableSortings): void
    {
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $slot = [
            'id' => Uuid::randomBytes(),
            'locked' => 1,
            'cms_block_id' => $blockId,
            'type' => 'product-listing',
            'slot' => 'content',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'version_id' => $versionId,
        ];

        $slotTranslationData = [
            'cms_slot_id' => $slot['id'],
            'cms_slot_version_id' => $versionId,
            'language_id' => $languageEn,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'config' => json_encode([
                'defaultSorting' => ['value' => $defaulSorting, 'source' => 'static'],
                'availableSortings' => [
                    'value' => $availableSortings,
                    'source' => 'static',
                ],
                'showSorting' => ['value' => true, 'source' => 'static'],
            ]),
        ];

        $connection->insert('cms_slot', $slot);

        $connection->insert('cms_slot_translation', $slotTranslationData);
    }
}
