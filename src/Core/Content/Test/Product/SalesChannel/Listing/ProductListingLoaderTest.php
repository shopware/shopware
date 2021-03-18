<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\Listing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @group slow
 */
class ProductListingLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var ProductListingLoader
     */
    private $productListingLoader;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var string
     */
    private $productId;

    /**
     * @var string
     */
    private $mainVariantId;

    private $optionIds = [];

    private $variantIds = [];

    private $groupIds = [];

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product.repository');
        $this->productListingLoader = $this->getContainer()->get(ProductListingLoader::class);
        $this->salesChannelContext = $this->createSalesChannelContext();
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        parent::setUp();
    }

    public function testMainVariant(): void
    {
        $this->createProduct([], true);

        $listing = $this->fetchListing();

        static::assertEquals(1, $listing->getTotal());

        /** @var ProductEntity $mainVariant */
        $mainVariant = $listing->getEntities()->first();

        static::assertEquals($this->mainVariantId, $mainVariant->getId());
        static::assertContains($this->optionIds['red'], $mainVariant->getOptionIds());
        static::assertContains($this->optionIds['l'], $mainVariant->getOptionIds());
        static::assertTrue($mainVariant->hasExtension('search'));
    }

    public function testMainVariantInactive(): void
    {
        $this->createProduct([], true);

        // update main variant to be inactive
        $this->repository->update([[
            'id' => $this->mainVariantId,
            'active' => false,
        ]], $this->salesChannelContext->getContext());

        $listing = $this->fetchListing();

        // another random variant of the product should be displayed
        static::assertEquals(1, $listing->getTotal());

        $variantId = $listing->first()->getId();

        static::assertNotEquals($this->mainVariantId, $variantId);
        static::assertContains($variantId, $this->variantIds);
        static::assertTrue($listing->first()->hasExtension('search'));
    }

    public function testMainVariantRemoved(): void
    {
        $this->createProduct([], true);

        $this->repository->delete([['id' => $this->mainVariantId]], $this->salesChannelContext->getContext());

        $listing = $this->fetchListing();

        // another random variant of the product should be displayed
        static::assertEquals(1, $listing->getTotal());

        $variantId = $listing->first()->getId();

        static::assertNotEquals($this->mainVariantId, $variantId);
        static::assertContains($variantId, $this->variantIds);
        static::assertTrue($listing->first()->hasExtension('search'));
    }

    public function testMainVariantOutOfStock(): void
    {
        $this->createProduct([], true);

        $this->systemConfigService->set(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            true,
            $this->salesChannelContext->getSalesChannel()->getId()
        );

        // update main variant to be out of stock
        $this->repository->update([[
            'id' => $this->mainVariantId,
            'stock' => 0,
            'isCloseout' => true,
        ]], $this->salesChannelContext->getContext());

        $listing = $this->fetchListing();

        // another random variant of the product should be displayed
        static::assertEquals(1, $listing->getTotal());

        $variantId = $listing->first()->getId();

        static::assertNotEquals($this->mainVariantId, $variantId);
        static::assertContains($variantId, $this->variantIds);
        static::assertTrue($listing->first()->hasExtension('search'));
    }

    public function testChangeProductConfigToMainVariant(): void
    {
        // no main variant will be set initially
        $this->createProduct(['color', 'size'], false);

        // update product with a main variant
        $this->repository->update([[
            'id' => $this->productId,
            'mainVariantId' => $this->mainVariantId,
            'configuratorGroupConfig' => [],
        ]], $this->salesChannelContext->getContext());

        $listing = $this->fetchListing();

        static::assertEquals(1, $listing->getTotal());

        // only main variant should be returned
        $mainVariant = $listing->getEntities()->first();

        static::assertEquals($this->mainVariantId, $mainVariant->getId());
        static::assertContains($this->optionIds['red'], $mainVariant->getOptionIds());
        static::assertContains($this->optionIds['l'], $mainVariant->getOptionIds());
        static::assertTrue($mainVariant->hasExtension('search'));
    }

    public function testChangeProductConfigToVariantGroups(): void
    {
        // main variant will be set initially
        $this->createProduct([], true);

        // update product with no main variant
        $this->repository->update([[
            'id' => $this->productId,
            'mainVariantId' => null,
            'configuratorGroupConfig' => $this->getListingConfiguration(['color', 'size']),
        ]], $this->salesChannelContext->getContext());

        $listing = $this->fetchListing();

        // all variants should be returned
        static::assertEquals(4, $listing->getTotal());

        $variants = $listing->getIds();

        static::assertContains($this->variantIds['redXl'], $variants);
        static::assertContains($this->variantIds['redL'], $variants);
        static::assertContains($this->variantIds['greenL'], $variants);
        static::assertContains($this->variantIds['greenXl'], $variants);

        foreach ($listing as $variant) {
            static::assertTrue($variant->hasExtension('search'));
        }
    }

    public function testMainVariantAndVariantGroups(): void
    {
        // main variant and variant groups be set initially
        $this->createProduct(['color', 'size'], true);

        $listing = $this->fetchListing();

        // only the main variant should be returned
        static::assertEquals(1, $listing->getTotal());

        $variantId = $listing->first()->getId();

        static::assertEquals($this->mainVariantId, $variantId);
        static::assertTrue($listing->first()->hasExtension('search'));
    }

    public function testAllVariants(): void
    {
        $this->createProduct(['size', 'color'], false);

        $listing = $this->fetchListing();

        // all variants should be returned
        static::assertEquals(4, $listing->getTotal());

        $variants = $listing->getIds();

        static::assertContains($this->variantIds['redXl'], $variants);
        static::assertContains($this->variantIds['redL'], $variants);
        static::assertContains($this->variantIds['greenL'], $variants);
        static::assertContains($this->variantIds['greenXl'], $variants);

        foreach ($listing as $variant) {
            static::assertTrue($variant->hasExtension('search'));
        }
    }

    private function fetchListing(): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $this->productId));
        $criteria->setTerm('example');

        return $this->productListingLoader->load($criteria, $this->salesChannelContext);
    }

    /**
     * @param string[] $listingProperties
     */
    private function createProduct(array $listingProperties, bool $hasMainVariant = false): void
    {
        $this->productId = Uuid::randomHex();

        $this->optionIds = [
            'red' => Uuid::randomHex(),
            'green' => Uuid::randomHex(),
            'xl' => Uuid::randomHex(),
            'l' => Uuid::randomHex(),
        ];

        $this->variantIds = [
            'redXl' => Uuid::randomHex(),
            'greenXl' => Uuid::randomHex(),
            'redL' => Uuid::randomHex(),
            'greenL' => Uuid::randomHex(),
        ];

        $this->variantIds['mainVariantId'] = $this->variantIds['redL'];

        $this->groupIds = [
            'color' => Uuid::randomHex(),
            'size' => Uuid::randomHex(),
        ];

        $this->mainVariantId = $this->variantIds['redL'];

        $config = $this->getListingConfiguration($listingProperties);

        $tax = ['id' => Uuid::randomHex(), 'name' => '19', 'taxRate' => 19];

        $data = [
            [
                'id' => $this->productId,
                'configuratorGroupConfig' => $config,
                'productNumber' => 'a.0',
                'manufacturer' => ['name' => 'test'],
                'tax' => $tax,
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
                ],
                'visibilities' => [
                    [
                        'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
            [
                'id' => $this->variantIds['redXl'],
                'productNumber' => 'a.1',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['xl']],
                ],
            ],
            [
                'id' => $this->variantIds['greenXl'],
                'productNumber' => 'a.3',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['xl']],
                ],
            ],
            [
                'id' => $this->variantIds['redL'],
                'productNumber' => 'a.5',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['l']],
                ],
            ],
            [
                'id' => $this->variantIds['greenL'],
                'productNumber' => 'a.7',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['l']],
                ],
            ],
        ];

        $this->addTaxDataToSalesChannel($this->salesChannelContext, $tax);

        $this->repository->create($data, $this->salesChannelContext->getContext());

        if ($hasMainVariant) {
            // udpate main variant
            $this->repository->update([
                [
                    'id' => $this->productId,
                    'mainVariantId' => $this->mainVariantId,
                ],
            ], $this->salesChannelContext->getContext());
        }
    }

    private function getListingConfiguration(array $listingProperties): array
    {
        $config = [];

        foreach ($listingProperties as $groupName) {
            $config[] = [
                'id' => $this->groupIds[$groupName],
                'expressionForListings' => true,
                'representation' => 'box', // box, select, image, color
            ];
        }

        return $config;
    }
}
