<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class VariantListingIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    private EntityRepository $repository;

    private string $productId;

    /**
     * @var array<string, string>
     */
    private array $optionIds = [];

    /**
     * @var array<string, string>
     */
    private array $groupIds = [];

    /**
     * @var array<string, string>
     */
    private array $variantIds = [];

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product.repository');
        $this->connection = $this->getContainer()->get(Connection::class);

        parent::setUp();
    }

    public function testSingleGroup(): void
    {
        $this->createProduct(['color']);

        $listing = $this->fetchListing();

        static::assertCount(2, $listing->ids);

        static::assertContains($this->optionIds['green'], $listing->optionIds);
        static::assertContains($this->optionIds['red'], $listing->optionIds);
    }

    public function testTwoGroups(): void
    {
        $this->createProduct(['color', 'size']);

        $listing = $this->fetchListing();

        static::assertCount(4, $listing->ids);

        static::assertContains($this->optionIds['green'], $listing->optionIds);
        static::assertContains($this->optionIds['red'], $listing->optionIds);

        static::assertContains($this->optionIds['xl'], $listing->optionIds);
        static::assertContains($this->optionIds['l'], $listing->optionIds);
    }

    public function testAllGroups(): void
    {
        $this->createProduct(['color', 'size', 'material']);

        $listing = $this->fetchListing();
        static::assertCount(8, $listing->ids);
    }

    public function testNoGroup(): void
    {
        $this->createProduct([]);

        $listing = $this->fetchListing();

        static::assertCount(1, $listing->ids);

        $listing = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) as id FROM product WHERE display_group IS NOT NULL AND product.id = :parentId',
            ['parentId' => Uuid::fromHexToBytes($this->productId)]
        );
        static::assertCount(0, $listing);
    }

    /**
     * @param string[] $listingProperties
     */
    private function createProduct(array $listingProperties): void
    {
        $this->productId = Uuid::randomHex();

        $this->optionIds = [
            'red' => Uuid::randomHex(),
            'green' => Uuid::randomHex(),
            'xl' => Uuid::randomHex(),
            'l' => Uuid::randomHex(),
            'iron' => Uuid::randomHex(),
            'steel' => Uuid::randomHex(),
        ];

        $this->variantIds = [
            'redXlIron' => Uuid::randomHex(),
            'redXlSteel' => Uuid::randomHex(),
            'greenXlIron' => Uuid::randomHex(),
            'greenXlSteel' => Uuid::randomHex(),
            'redLIron' => Uuid::randomHex(),
            'redLSteel' => Uuid::randomHex(),
            'greenLIron' => Uuid::randomHex(),
            'greenLSteel' => Uuid::randomHex(),
        ];

        $this->groupIds = [
            'color' => Uuid::randomHex(),
            'size' => Uuid::randomHex(),
            'material' => Uuid::randomHex(),
        ];

        $config = [];
        foreach ($listingProperties as $groupName) {
            $config[] = [
                'id' => $this->groupIds[$groupName],
                'expressionForListings' => true,
                'representation' => 'box', // box, select, image, color
            ];
        }

        $data = [
            [
                'id' => $this->productId,
                'variantListingConfig' => [
                    'displayParent' => null,
                    'mainVariantId' => null,
                    'configuratorGroupConfig' => $config,
                ],
                'productNumber' => 'a.0',
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 19, 'name' => 'test'],
                'stock' => 10,
                'name' => 'example',
                'active' => true,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true],
                ],
                'configuratorSettings' => [
                    [
                        'option' => [
                            'id' => $this->optionIds['red'],
                            'name' => 'Red',
                            'group' => [
                                'id' => $this->groupIds['color'],
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['green'],
                            'name' => 'Green',
                            'group' => [
                                'id' => $this->groupIds['color'],
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['xl'],
                            'name' => 'XL',
                            'group' => [
                                'id' => $this->groupIds['size'],
                                'name' => 'size',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['l'],
                            'name' => 'L',
                            'group' => [
                                'id' => $this->groupIds['size'],
                                'name' => 'size',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['iron'],
                            'name' => 'Iron',
                            'group' => [
                                'id' => $this->groupIds['material'],
                                'name' => 'material',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['steel'],
                            'name' => 'Steel',
                            'group' => [
                                'id' => $this->groupIds['material'],
                                'name' => 'material',
                            ],
                        ],
                    ],
                ],
                'visibilities' => [
                    [
                        'salesChannelId' => TestDefaults::SALES_CHANNEL,
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
            [
                'id' => $this->variantIds['redXlIron'],
                'productNumber' => 'a.1',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['xl']],
                    ['id' => $this->optionIds['iron']],
                ],
            ],
            [
                'id' => $this->variantIds['redXlSteel'],
                'productNumber' => 'a.2',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['xl']],
                    ['id' => $this->optionIds['steel']],
                ],
            ],
            [
                'id' => $this->variantIds['greenXlIron'],
                'productNumber' => 'a.3',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['xl']],
                    ['id' => $this->optionIds['iron']],
                ],
            ],
            [
                'id' => $this->variantIds['greenXlSteel'],
                'productNumber' => 'a.4',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['xl']],
                    ['id' => $this->optionIds['steel']],
                ],
            ],
            [
                'id' => $this->variantIds['redLIron'],
                'productNumber' => 'a.5',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['l']],
                    ['id' => $this->optionIds['iron']],
                ],
            ],
            [
                'id' => $this->variantIds['redLSteel'],
                'productNumber' => 'a.6',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['l']],
                    ['id' => $this->optionIds['steel']],
                ],
            ],
            [
                'id' => $this->variantIds['greenLIron'],
                'productNumber' => 'a.7',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['l']],
                    ['id' => $this->optionIds['iron']],
                ],
            ],
            [
                'id' => $this->variantIds['greenLSteel'],
                'productNumber' => 'a.8',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['l']],
                    ['id' => $this->optionIds['steel']],
                ],
            ],
        ];

        $this->repository->create($data, Context::createDefaultContext());

        $this->runWorker();
    }

    private function fetchListing(): Listing
    {
        $listing = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) as id, option_ids
             FROM product
             WHERE product.parent_id = :parentId
             AND display_group IS NOT NULL
             GROUP BY display_group',
            ['parentId' => Uuid::fromHexToBytes($this->productId)]
        );

        /** @var array<array<string>> $optionIds */
        $optionIds = array_map(fn ($item) => json_decode((string) $item['option_ids'], true, 512, \JSON_THROW_ON_ERROR), $listing);

        if (!empty($optionIds)) {
            $optionIds = array_merge(...$optionIds);
        }

        return new Listing(array_column($listing, 'id'), $optionIds);
    }
}

/**
 * @internal
 */
class Listing
{
    /**
     * @param string[] $ids
     * @param string[] $optionIds
     */
    public function __construct(
        public array $ids,
        public array $optionIds
    ) {
    }
}
