<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1700746995ReplaceSortingOptionKeysWithSortingOptionIds;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1700746995ReplaceSortingOptionKeysWithSortingOptionIds::class)]
class Migration1700746995ReplaceSortingOptionKeysWithSortingOptionIdsTest extends TestCase
{
    use MigrationTestTrait;

    private Migration1700746995ReplaceSortingOptionKeysWithSortingOptionIds $migration;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->migration = new Migration1700746995ReplaceSortingOptionKeysWithSortingOptionIds();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $this->addSalesChannelSorting();

        $categoryValues = $this->addCategoryConfig();
        $categoryCombinedKey = $categoryValues[0];
        $productListingSlot = $categoryValues[1];

        $sortingIds = $this->getSortingIds();

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $defaultSortingConfigs = $this->connection->fetchAllAssociative(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :configKey',
            ['configKey' => 'core.listing.defaultSorting']
        );

        static::assertSame(
            $sortingIds['name-asc'],
            Uuid::fromHexToBytes(json_decode($defaultSortingConfigs[0]['configuration_value'], true)['_value'])
        );
        static::assertSame(
            $sortingIds['price-desc'],
            Uuid::fromHexToBytes(json_decode($defaultSortingConfigs[1]['configuration_value'], true)['_value'])
        );
        static::assertSame(
            $sortingIds['topseller'],
            Uuid::fromHexToBytes(json_decode($defaultSortingConfigs[2]['configuration_value'], true)['_value'])
        );

        $language = [
            $categoryCombinedKey[2][0]['id'],
            $categoryCombinedKey[2][1]['id'],
        ];

        $categoryConfigs0 = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT slot_config FROM category_translation
                WHERE category_id = :categoryId
                  AND category_version_id = :categoryVersionId
                  AND language_id = :langId;
            SQL,
            [
                'categoryId' => $categoryCombinedKey[0],
                'categoryVersionId' => $categoryCombinedKey[1],
                'langId' => $language[0],
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
                'categoryId' => $categoryCombinedKey[0],
                'categoryVersionId' => $categoryCombinedKey[1],
                'langId' => $language[1],
            ]
        );
        static::assertNotFalse($categoryConfigs1);

        $categoryConfig0 = json_decode($categoryConfigs0['slot_config'], true);
        static::assertSame(
            $sortingIds['name-asc'],
            Uuid::fromHexToBytes($categoryConfig0[Uuid::fromBytesToHex($productListingSlot)]['defaultSorting']['value'])
        );

        $availableSortings = $categoryConfig0[Uuid::fromBytesToHex($productListingSlot)]['availableSortings']['value'];
        static::assertSame(4, $availableSortings[Uuid::fromBytesToHex($sortingIds['name-asc'])]);
        static::assertSame(2, $availableSortings[Uuid::fromBytesToHex($sortingIds['price-asc'])]);

        $categoryConfig1 = json_decode($categoryConfigs1['slot_config'], true);

        static::assertSame(
            $sortingIds['name-desc'],
            Uuid::fromHexToBytes($categoryConfig1[Uuid::fromBytesToHex($productListingSlot)]['defaultSorting']['value'])
        );

        $availableSortings = $categoryConfig1[Uuid::fromBytesToHex($productListingSlot)]['availableSortings']['value'];
        static::assertSame(4, $availableSortings[Uuid::fromBytesToHex($sortingIds['name-desc'])]);
        static::assertSame(2, $availableSortings[Uuid::fromBytesToHex($sortingIds['topseller'])]);
        static::assertSame(1, $availableSortings[Uuid::fromBytesToHex($sortingIds['price-desc'])]);
    }

    public function testMigrationForCorruptedEntries(): void
    {
        $this->addCorruptedSalesChannelSorting();

        $categoryValues = $this->addCorruptedCategoryConfig();
        $categoryCombinedKey = $categoryValues[0];
        $productListingSlot = $categoryValues[1];

        $sortingIds = $this->getSortingIds();

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $defaultSortingConfigs = $this->connection->fetchAllAssociative(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :configKey',
            ['configKey' => 'core.listing.defaultSorting']
        );

        static::assertSame(
            $sortingIds['name-asc'],
            Uuid::fromHexToBytes(json_decode($defaultSortingConfigs[0]['configuration_value'], true)['_value'])
        );
        static::assertSame(
            [
                '_valu' => 'price-desc',
            ],
            json_decode($defaultSortingConfigs[1]['configuration_value'], true)
        );
        static::assertSame(
            [
                '_value' => 'njdsfk;nsjdgk;',
            ],
            json_decode($defaultSortingConfigs[2]['configuration_value'], true)
        );

        $language = [
            $categoryCombinedKey[2][0]['id'],
            $categoryCombinedKey[2][1]['id'],
        ];

        $categoryConfigs0 = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT slot_config FROM category_translation
                WHERE category_id = :categoryId
                  AND category_version_id = :categoryVersionId
                  AND language_id = :langId;
            SQL,
            [
                'categoryId' => $categoryCombinedKey[0],
                'categoryVersionId' => $categoryCombinedKey[1],
                'langId' => $language[0],
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
                'categoryId' => $categoryCombinedKey[0],
                'categoryVersionId' => $categoryCombinedKey[1],
                'langId' => $language[1],
            ]
        );
        static::assertNotFalse($categoryConfigs1);

        $categoryConfig0 = json_decode($categoryConfigs0['slot_config'], true);
        static::assertSame(
            'bvhlbjkbk',
            $categoryConfig0[Uuid::fromBytesToHex($productListingSlot)]['defaultSorting']['value']
        );

        $availableSortings = $categoryConfig0[Uuid::fromBytesToHex($productListingSlot)]['availableSortings']['value'];
        static::assertSame(4, $availableSortings['klm;mkl;ml']);
        static::assertSame(2, $availableSortings[Uuid::fromBytesToHex($sortingIds['price-asc'])]);

        $categoryConfig1 = json_decode($categoryConfigs1['slot_config'], true);
        static::assertSame(
            $sortingIds['name-desc'],
            Uuid::fromHexToBytes($categoryConfig1[Uuid::fromBytesToHex($productListingSlot)]['defaultSorting']['value'])
        );

        $availableSortings = $categoryConfig1[Uuid::fromBytesToHex($productListingSlot)]['availableSortings']['value'];
        static::assertSame(4, $availableSortings['']);
        static::assertSame(2, $availableSortings['asd']);
        static::assertSame(1, $availableSortings['12883120812']);
    }

    public function testMigrationForCorruptedEntriesWithWrongStructure(): void
    {
        $this->addCorruptedSalesChannelSorting();

        $categoryValues = $this->addCorruptedCategoryConfigWithWrongStructure();
        $categoryCombinedKey = $categoryValues[0];
        $productListingSlot = $categoryValues[1];

        $sortingIds = $this->getSortingIds();

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $defaultSortingConfigs = $this->connection->fetchAllAssociative(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :configKey',
            ['configKey' => 'core.listing.defaultSorting']
        );

        static::assertSame(
            $sortingIds['name-asc'],
            Uuid::fromHexToBytes(json_decode($defaultSortingConfigs[0]['configuration_value'], true)['_value'])
        );
        static::assertSame(
            [
                '_valu' => 'price-desc',
            ],
            json_decode($defaultSortingConfigs[1]['configuration_value'], true)
        );
        static::assertSame(
            [
                '_value' => 'njdsfk;nsjdgk;',
            ],
            json_decode($defaultSortingConfigs[2]['configuration_value'], true)
        );

        $language = [
            $categoryCombinedKey[2][0]['id'],
            $categoryCombinedKey[2][1]['id'],
        ];

        $categoryConfigs0 = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT slot_config FROM category_translation
                WHERE category_id = :categoryId
                  AND category_version_id = :categoryVersionId
                  AND language_id = :langId;
            SQL,
            [
                'categoryId' => $categoryCombinedKey[0],
                'categoryVersionId' => $categoryCombinedKey[1],
                'langId' => $language[0],
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
                'categoryId' => $categoryCombinedKey[0],
                'categoryVersionId' => $categoryCombinedKey[1],
                'langId' => $language[1],
            ]
        );
        static::assertNotFalse($categoryConfigs1);

        $categoryConfig0 = json_decode($categoryConfigs0['slot_config'], true);
        static::assertNull(
            $categoryConfig0[Uuid::fromBytesToHex($productListingSlot)]['defaultSorting']['value']
        );

        $availableSortings = $categoryConfig0[Uuid::fromBytesToHex($productListingSlot)]['availableSortings']['value'];
        static::assertCount(0, $availableSortings);

        $categoryConfig1 = json_decode($categoryConfigs1['slot_config'], true);
        static::assertSame(
            $sortingIds['name-desc'],
            Uuid::fromHexToBytes($categoryConfig1[Uuid::fromBytesToHex($productListingSlot)]['defaultSorting']['value'])
        );

        $availableSortings = $categoryConfig1[Uuid::fromBytesToHex($productListingSlot)]['availableSortings']['value'];
        static::assertSame(4, $availableSortings[0]);
        static::assertSame(2, $availableSortings[1]);
        static::assertSame(1, $availableSortings[2]);
    }

    private function addSalesChannelSorting(): void
    {
        $sortings = [
            [
                'id' => Uuid::randomBytes(),
                'configuration_key' => 'core.listing.defaultSorting',
                'configuration_value' => '{"_value": "price-desc"}',
                'created_at' => '2023-11-09 11:56:05.369',
            ],
            [
                'id' => Uuid::randomBytes(),
                'configuration_key' => 'core.listing.defaultSorting',
                'configuration_value' => '{"_value": "topseller"}',
                'created_at' => '2023-11-09 11:56:05.369',
            ],
        ];

        $this->connection->insert('system_config', $sortings[0]);
        $this->connection->insert('system_config', $sortings[1]);
    }

    private function addCorruptedSalesChannelSorting(): void
    {
        $sortings = [
            [
                'id' => Uuid::randomBytes(),
                'configuration_key' => 'core.listing.defaultSorting',
                'configuration_value' => '{"_valu": "price-desc"}',
                'created_at' => '2023-11-09 11:56:05.369',
            ],
            [
                'id' => Uuid::randomBytes(),
                'configuration_key' => 'core.listing.defaultSorting',
                'configuration_value' => '{"_value": "njdsfk;nsjdgk;"}',
                'created_at' => '2023-11-09 11:56:05.369',
            ],
        ];

        $this->connection->insert('system_config', $sortings[0]);
        $this->connection->insert('system_config', $sortings[1]);
    }

    /**
     * @return array<string|int, mixed>
     */
    private function addCategoryConfig(): array
    {
        $productListingSlotId = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT id FROM cms_slot WHERE type = 'product-listing';
        SQL)[0]['id'];

        $categoryId = $this->connection->fetchAllAssociative('SELECT id, version_id FROM category;')[0];

        $languageIds = $this->connection->fetchAllAssociative('SELECT id FROM language;');

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
            Uuid::fromBytesToHex($productListingSlotId) => [
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
            ],
        ];

        $slotConfig[Uuid::fromBytesToHex($productListingSlotId)]['defaultSorting'] = [
            'value' => 'name-asc',
            'source' => 'static',
        ];
        $slotConfig[Uuid::fromBytesToHex($productListingSlotId)]['availableSortings'] = [
            'value' => [
                'name-asc' => 4,
                'price-asc' => 2,
            ],
            'source' => 'static',
        ];

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
                'langId' => $languageIds[0]['id'],
            ]
        );

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
                'langId' => $languageIds[1]['id'],
            ]
        );

        return [[$categoryId['id'], $categoryId['version_id'], $languageIds], $productListingSlotId];
    }

    /**
     * @return array<string|int, mixed>
     */
    private function addCorruptedCategoryConfig(): array
    {
        $productListingSlotId = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT id FROM cms_slot WHERE type = 'product-listing';
        SQL)[0]['id'];

        $categoryId = $this->connection->fetchAllAssociative('SELECT id, version_id FROM category;')[0];

        $languageIds = $this->connection->fetchAllAssociative('SELECT id FROM language;');

        $slotConfig = [
            'slotConfig' => [
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
                Uuid::fromBytesToHex($productListingSlotId) => [
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
                ],
            ],
        ];

        $slotConfig[Uuid::fromBytesToHex($productListingSlotId)]['defaultSorting'] = [
            'value' => 'bvhlbjkbk',
            'source' => 'static',
        ];
        $slotConfig[Uuid::fromBytesToHex($productListingSlotId)]['availableSortings'] = [
            'value' => [
                'klm;mkl;ml' => 4,
                'price-asc' => 2,
            ],
            'source' => 'static',
        ];

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
                'langId' => $languageIds[0]['id'],
            ]
        );

        $slotConfig[Uuid::fromBytesToHex($productListingSlotId)]['defaultSorting'] = [
            'value' => 'name-desc',
            'source' => 'static',
        ];
        $slotConfig[Uuid::fromBytesToHex($productListingSlotId)]['availableSortings'] = [
            'value' => [
                '' => 4,
                'asd' => 2,
                '12883120812' => 1,
            ],
            'source' => 'static',
        ];

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
                'langId' => $languageIds[1]['id'],
            ]
        );

        return [[$categoryId['id'], $categoryId['version_id'], $languageIds], $productListingSlotId];
    }

    /**
     * @return array<string|int, mixed>
     */
    private function addCorruptedCategoryConfigWithWrongStructure(): array
    {
        $productListingSlotId = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT id FROM cms_slot WHERE type = 'product-listing';
        SQL)[0]['id'];

        $categoryId = $this->connection->fetchAllAssociative('SELECT id, version_id FROM category;')[0];

        $languageIds = $this->connection->fetchAllAssociative('SELECT id FROM language;');

        $slotConfig = [
            'slotConfig' => [
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
                Uuid::fromBytesToHex($productListingSlotId) => [
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
                ],
            ],
        ];

        $slotConfig[Uuid::fromBytesToHex($productListingSlotId)]['defaultSorting'] = [
            'value' => null,
            'source' => 'static',
        ];
        // missing availableSortings key
        unset($slotConfig[Uuid::fromBytesToHex($productListingSlotId)]['availableSortings']);

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
                'langId' => $languageIds[0]['id'],
            ]
        );

        $slotConfig[Uuid::fromBytesToHex($productListingSlotId)]['defaultSorting'] = [
            'value' => 'name-desc',
            'source' => 'static',
        ];
        // wrong structure
        $slotConfig[Uuid::fromBytesToHex($productListingSlotId)]['availableSortings'] = [
            'value' => [
                4,
                2,
                1,
            ],
            'source' => 'static',
        ];

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
                'langId' => $languageIds[1]['id'],
            ]
        );

        return [[$categoryId['id'], $categoryId['version_id'], $languageIds], $productListingSlotId];
    }

    /**
     * @return string[]
     */
    private function getSortingIds(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT url_key, id FROM product_sorting');
    }
}
