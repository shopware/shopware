<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1711461572ReplaceSortingOptionKeysWithSortingOptionIds;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1711461572ReplaceSortingOptionKeysWithSortingOptionIds::class)]
class Migration1711461572ReplaceSortingOptionKeysWithSortingOptionIdsTest extends TestCase
{
    use MigrationTestTrait;

    private Migration1711461572ReplaceSortingOptionKeysWithSortingOptionIds $migration;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->migration = new Migration1711461572ReplaceSortingOptionKeysWithSortingOptionIds();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        [$categoryId, $versionId, $languages] = $this->addCategoryConfig();

        $sortingIds = $this->getSortingIds();

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $categoryConfigs0 = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT slot_config FROM category_translation
                WHERE category_id = :categoryId
                  AND category_version_id = :categoryVersionId
                  AND language_id = :langId;
            SQL,
            [
                'categoryId' => $categoryId,
                'categoryVersionId' => $versionId,
                'langId' => $languages[0],
            ]
        );
        static::assertNotFalse($categoryConfigs0);

        $categoryConfigs1 = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT slot_config FROM category_translation
                WHERE category_id = :categoryId
                  AND category_version_id = :categoryVersionId
                  AND language_id = :langId;
            SQL,
            [
                'categoryId' => $categoryId,
                'categoryVersionId' => $versionId,
                'langId' => $languages[1],
            ]
        );
        static::assertNotFalse($categoryConfigs1);

        $productListingSlotIds = $this->connection->fetchFirstColumn(<<<'SQL'
            SELECT id FROM cms_slot WHERE type = 'product-listing';
        SQL);

        $categoryConfig0 = json_decode($categoryConfigs0['slot_config'], true);

        foreach ($productListingSlotIds as $productListingSlot) {
            static::assertSame(
                $sortingIds['name-asc'],
                Uuid::fromHexToBytes($categoryConfig0[Uuid::fromBytesToHex($productListingSlot)]['defaultSorting']['value'])
            );

            $availableSortings = $categoryConfig0[Uuid::fromBytesToHex($productListingSlot)]['availableSortings']['value'];
            static::assertSame(4, $availableSortings[Uuid::fromBytesToHex($sortingIds['name-asc'])]);
            static::assertSame(2, $availableSortings[Uuid::fromBytesToHex($sortingIds['price-asc'])]);
        }

        $categoryConfig1 = json_decode($categoryConfigs1['slot_config'], true);

        foreach ($productListingSlotIds as $productListingSlot) {
            static::assertSame(
                $sortingIds['name-desc'],
                Uuid::fromHexToBytes($categoryConfig1[Uuid::fromBytesToHex($productListingSlot)]['defaultSorting']['value'])
            );

            $availableSortings = $categoryConfig1[Uuid::fromBytesToHex($productListingSlot)]['availableSortings']['value'];
            static::assertSame(4, $availableSortings[Uuid::fromBytesToHex($sortingIds['name-desc'])]);
            static::assertSame(2, $availableSortings[Uuid::fromBytesToHex($sortingIds['topseller'])]);
            static::assertSame(1, $availableSortings[Uuid::fromBytesToHex($sortingIds['price-desc'])]);
        }
    }

    /**
     * @return array{0: string, 1: string, 2: list<string>}
     */
    private function addCategoryConfig(): array
    {
        $productListingSlotIds = $this->connection->fetchFirstColumn(<<<'SQL'
            SELECT id FROM cms_slot WHERE type = 'product-listing';
        SQL);

        static::assertGreaterThan(1, $productListingSlotIds);

        $categoryId = $this->connection->fetchAllAssociative('SELECT id, version_id FROM category;')[0];

        $languageIds = $this->connection->fetchFirstColumn('SELECT id FROM language;');

        $slotConfig = [
            '018bfcbcf38d7301a928a371e25ebb2e' => [
                'url' => [
                    'value' => null,
                    'source' => 'static',
                ],
                'media' => [
                    'value' => 'category.media',
                    'source' => 'mapped',
                ],
                'newTab' => [
                    'value' => false,
                    'source' => 'static',
                ],
                'minHeight' => [
                    'value' => '320px',
                    'source' => 'static',
                ],
                'displayMode' => [
                    'value' => 'cover',
                    'source' => 'static',
                ],
                'verticalAlign' => [
                    'value' => null,
                    'source' => 'static',
                ],
                'horizontalAlign' => [
                    'value' => null,
                    'source' => 'static',
                ],
            ],
            '018bfcbcf38d7301a928a371e25fc821' => [
                'content' => [
                    'value' => 'category.description',
                    'source' => 'mapped',
                ],
                'verticalAlign' => [
                    'value' => null,
                    'source' => 'static',
                ],
            ],
        ];

        foreach ($productListingSlotIds as $productListingSlotId) {
            $slotConfig[Uuid::fromBytesToHex($productListingSlotId)] = [
                'filters' => [
                    'value' => 'manufacturer-filter,rating-filter,price-filter,shipping-free-filter,property-filter',
                    'source' => 'static',
                ],
                'boxLayout' => [
                    'value' => 'standard',
                    'source' => 'static',
                ],
                'showSorting' => [
                    'value' => true,
                    'source' => 'static',
                ],
                'useCustomSorting' => [
                    'value' => true,
                    'source' => 'static',
                ],
                'propertyWhitelist' => [
                    'value' => [],
                    'source' => 'static',
                ],
                'defaultSorting' => [
                    'value' => 'name-asc',
                    'source' => 'static',
                ],
                'availableSortings' => [
                    'value' => [
                        'name-asc' => 4,
                        'price-asc' => 2,
                    ],
                    'source' => 'static',
                ],
            ];
        }

        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE category_translation SET slot_config = :slotConfig
                WHERE category_id = :categoryId
                  AND category_version_id = :categoryVersionId
                  AND language_id = :langId;
            SQL,
            [
                'slotConfig' => json_encode($slotConfig),
                'categoryId' => $categoryId['id'],
                'categoryVersionId' => $categoryId['version_id'],
                'langId' => $languageIds[0],
            ]
        );

        foreach ($productListingSlotIds as $productListingSlotId) {
            $slotConfig[Uuid::fromBytesToHex($productListingSlotId)]['defaultSorting'] = [
                'value' => 'name-desc',
                'source' => 'static',
            ];
            $slotConfig[Uuid::fromBytesToHex($productListingSlotId)]['availableSortings'] = [
                'value' => [
                    'name-desc' => 4,
                    'topseller' => 2,
                    'price-desc' => 1,
                ],
                'source' => 'static',
            ];
        }

        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE category_translation SET slot_config = :slotConfig
                WHERE category_id = :categoryId
                  AND category_version_id = :categoryVersionId
                  AND language_id = :langId;
            SQL,
            [
                'slotConfig' => json_encode($slotConfig),
                'categoryId' => $categoryId['id'],
                'categoryVersionId' => $categoryId['version_id'],
                'langId' => $languageIds[1],
            ]
        );

        return [$categoryId['id'], $categoryId['version_id'], $languageIds];
    }

    /**
     * @return string[]
     */
    private function getSortingIds(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT url_key, id FROM product_sorting');
    }
}
