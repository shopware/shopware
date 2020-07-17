<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class VariantListingIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var string
     */
    private $productId;

    /**
     * @var string
     */
    private $salesChannelId;

    private $optionIds = [];

    private $groupIds = [];

    private $variantIds = [];

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

        $listing = $this->connection->fetchAll(
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
                'configuratorGroupConfig' => $config,
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
                        'salesChannelId' => Defaults::SALES_CHANNEL,
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
    }

    private function fetchListing(): Listing
    {
        $listing = $this->connection->fetchAll(
            'SELECT LOWER(HEX(id)) as id, option_ids
             FROM product
             WHERE product.parent_id = :parentId
             AND display_group IS NOT NULL
             GROUP BY display_group',
            ['parentId' => Uuid::fromHexToBytes($this->productId)]
        );

        $optionIds = array_map(function ($item) {
            return json_decode((string) $item['option_ids'], true);
        }, $listing);

        if (!empty($optionIds)) {
            $optionIds = array_merge(...$optionIds);
        }

        return new Listing(array_column($listing, 'id'), $optionIds);
    }
}

class Listing
{
    /**
     * @var array
     */
    public $ids;

    /**
     * @var array
     */
    public $optionIds;

    public function __construct(array $ids, array $optionIds)
    {
        $this->ids = $ids;
        $this->optionIds = $optionIds;
    }
}
