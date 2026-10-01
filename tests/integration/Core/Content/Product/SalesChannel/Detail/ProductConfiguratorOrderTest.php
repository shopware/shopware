<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Detail;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('inventory')]
class ProductConfiguratorOrderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $repository;

    /**
     * @var SalesChannelRepository<SalesChannelProductCollection>
     */
    private SalesChannelRepository $salesChannelProductRepository;

    private SalesChannelContext $context;

    private ProductConfiguratorLoader $loader;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('product.repository');

        $this->salesChannelProductRepository = static::getContainer()->get('sales_channel.product.repository');

        $this->context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create('test', TestDefaults::SALES_CHANNEL);

        $this->loader = static::getContainer()->get(ProductConfiguratorLoader::class);

        parent::setUp();
    }

    public function testDefaultOrder(): void
    {
        $groupNames = $this->getOrder();
        static::assertSame(['a', 'b', 'c', 'd', 'e', 'f'], $groupNames);
    }

    public function testGroupPositionOrder(): void
    {
        $groupNames = $this->getOrder(['f', 'e', 'd', 'c', 'b', 'a']);
        static::assertSame(['f', 'e', 'd', 'c', 'b', 'a'], $groupNames);
    }

    public function testConfiguratorGroupConfigOrder(): void
    {
        $groupNames = $this->getOrder(null, ['f', 'e', 'd', 'c', 'b', 'a']);
        static::assertSame(['f', 'e', 'd', 'c', 'b', 'a'], $groupNames);
    }

    public function testConfiguratorGroupConfigOverrideOrder(): void
    {
        $groupNames = $this->getOrder(['f', 'b', 'c', 'd', 'a', 'e'], ['f', 'e', 'd', 'c', 'b', 'a']);
        static::assertSame(['f', 'e', 'd', 'c', 'b', 'a'], $groupNames);
    }

    /**
     * When a variant carries an option id that is missing from
     * `product_configurator_setting`, the remaining configurator options must
     * stay combinable for in-stock variants instead of being greyed out.
     */
    public function testVariantsRemainCombinableWhenOptionIsMissingFromConfiguratorSetting(): void
    {
        // The variants are on clearance (isCloseout). Make sure the storefront's
        // "hide closeout products when out of stock" setting does not filter the
        // in-stock variant out of the sales-channel result, so the test loads the
        // data deterministically regardless of the environment default.
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutProductsWhenOutOfStock', false);

        $productId = Uuid::randomHex();
        $redSmallId = Uuid::randomHex();
        $redMediumId = Uuid::randomHex();
        $blueSmallId = Uuid::randomHex();
        $blueMediumId = Uuid::randomHex();
        $tax = ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'test'];

        $colorGroupId = Uuid::randomHex();
        $sizeGroupId = Uuid::randomHex();

        $redOptionId = Uuid::randomHex();
        $blueOptionId = Uuid::randomHex();
        $smallOptionId = Uuid::randomHex();
        $mediumOptionId = Uuid::randomHex();

        // Write the Blue property group option up front so the variants below
        // can reference it without a product_configurator_setting row.
        $propertyGroupOptionRepository = static::getContainer()->get('property_group_option.repository');
        $propertyGroupOptionRepository->create([
            [
                'id' => $blueOptionId,
                'name' => 'Blue',
                'group' => [
                    'id' => $colorGroupId,
                    'name' => 'Color',
                    'position' => 1,
                ],
            ],
        ], Context::createDefaultContext());

        $productData = [
            [
                'id' => $productId,
                'name' => 'Test product',
                'productNumber' => 'configurator-missing-setting-parent',
                'manufacturer' => ['name' => 'test'],
                'tax' => $tax,
                'stock' => 10,
                'active' => true,
                'isCloseout' => true,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true]],
                'configuratorSettings' => [
                    $this->createConfiguratorSetting($redOptionId, 'Red', $colorGroupId, 'Color', 1),
                    // Blue is intentionally missing from the configurator settings.
                    $this->createConfiguratorSetting($smallOptionId, 'Small', $sizeGroupId, 'Size', 2),
                    $this->createConfiguratorSetting($mediumOptionId, 'Medium', $sizeGroupId, 'Size', 2),
                ],
                'visibilities' => [
                    [
                        'salesChannelId' => TestDefaults::SALES_CHANNEL,
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
            [
                'id' => $redSmallId,
                'productNumber' => 'configurator-missing-setting-red-small',
                'stock' => 5,
                'active' => true,
                'parentId' => $productId,
                'options' => [['id' => $redOptionId], ['id' => $smallOptionId]],
            ],
            [
                'id' => $redMediumId,
                'productNumber' => 'configurator-missing-setting-red-medium',
                'stock' => 5,
                'active' => true,
                'parentId' => $productId,
                'options' => [['id' => $redOptionId], ['id' => $mediumOptionId]],
            ],
            [
                'id' => $blueSmallId,
                'productNumber' => 'configurator-missing-setting-blue-small',
                'stock' => 5,
                'active' => true,
                'parentId' => $productId,
                'options' => [['id' => $blueOptionId], ['id' => $smallOptionId]],
            ],
            [
                'id' => $blueMediumId,
                'productNumber' => 'configurator-missing-setting-blue-medium',
                'stock' => 5,
                'active' => true,
                'parentId' => $productId,
                'options' => [['id' => $blueOptionId], ['id' => $mediumOptionId]],
            ],
        ];

        $this->repository->create($productData, Context::createDefaultContext());
        $this->addTaxDataToSalesChannel($this->context, $tax);

        // Load a Blue variant; Blue has no configurator setting row.
        $salesChannelProduct = $this->salesChannelProductRepository
            ->search(new Criteria([$blueSmallId]), $this->context)
            ->getEntities()
            ->first();
        static::assertInstanceOf(SalesChannelProductEntity::class, $salesChannelProduct);

        $groups = $this->loader->load($salesChannelProduct, $this->context);

        $sizeGroup = $groups->get($sizeGroupId);
        static::assertInstanceOf(PropertyGroupEntity::class, $sizeGroup);
        $sizeOptions = $sizeGroup->getOptions();
        static::assertNotNull($sizeOptions);

        $smallOption = $sizeOptions->get($smallOptionId);
        static::assertNotNull($smallOption);
        static::assertTrue(
            $smallOption->getCombinable(),
            'Small size must remain combinable on a variant whose color option is missing from product_configurator_setting.'
        );

        $mediumOption = $sizeOptions->get($mediumOptionId);
        static::assertNotNull($mediumOption);
        static::assertTrue(
            $mediumOption->getCombinable(),
            'Medium size must remain combinable on a variant whose color option is missing from product_configurator_setting.'
        );
    }

    public function testGroupsWithoutAvailableOptionsAreRemoved(): void
    {
        $productId = Uuid::randomHex();
        $variantId = Uuid::randomHex();
        $tax = ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'test'];

        $colorGroupId = Uuid::randomHex();
        $sizeGroupId = Uuid::randomHex();
        $materialGroupId = Uuid::randomHex();

        $redOptionId = Uuid::randomHex();
        $blueOptionId = Uuid::randomHex();
        $smallOptionId = Uuid::randomHex();
        $largeOptionId = Uuid::randomHex();
        $cottonOptionId = Uuid::randomHex();
        $woolOptionId = Uuid::randomHex();

        $this->repository->create([
            [
                'id' => $productId,
                'name' => 'Test product',
                'productNumber' => 'configurator-parent',
                'manufacturer' => ['name' => 'test'],
                'tax' => $tax,
                'stock' => 10,
                'active' => true,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true]],
                'configuratorSettings' => [
                    $this->createConfiguratorSetting($redOptionId, 'Red', $colorGroupId, 'Color', 1),
                    $this->createConfiguratorSetting($blueOptionId, 'Blue', $colorGroupId, 'Color', 1),
                    $this->createConfiguratorSetting($smallOptionId, 'Small', $sizeGroupId, 'Size', 2),
                    $this->createConfiguratorSetting($largeOptionId, 'Large', $sizeGroupId, 'Size', 2),
                    $this->createConfiguratorSetting($cottonOptionId, 'Cotton', $materialGroupId, 'Material', 3),
                    $this->createConfiguratorSetting($woolOptionId, 'Wool', $materialGroupId, 'Material', 3),
                ],
                'visibilities' => [
                    [
                        'salesChannelId' => TestDefaults::SALES_CHANNEL,
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
            [
                'id' => $variantId,
                'productNumber' => 'configurator-variant',
                'stock' => 10,
                'active' => true,
                'parentId' => $productId,
                'options' => [
                    ['id' => $redOptionId],
                    ['id' => $smallOptionId],
                ],
            ],
        ], Context::createDefaultContext());
        $this->addTaxDataToSalesChannel($this->context, $tax);

        $criteria = new Criteria([$variantId]);
        /** @var SalesChannelProductEntity $salesChannelProduct */
        $salesChannelProduct = $this->salesChannelProductRepository->search($criteria, $this->context)->getEntities()->first();

        static::assertInstanceOf(SalesChannelProductEntity::class, $salesChannelProduct);

        $groups = $this->loader->load($salesChannelProduct, $this->context);

        static::assertSame(['Color', 'Size'], array_values(array_map(
            static fn (PropertyGroupEntity $propertyGroupEntity) => $propertyGroupEntity->getName(),
            $groups->getElements()
        )));
        static::assertNull($groups->get($materialGroupId));
    }

    /**
     * @param array<string, string> $groupIds
     */
    private function shuffle(array &$groupIds): void
    {
        $keys = array_keys($groupIds);
        shuffle($keys);
        $shuffled = [];
        foreach ($keys as $key) {
            $shuffled[$key] = $groupIds[$key];
        }
        $groupIds = $shuffled;
    }

    /**
     * @param array<string>|null $groupPositionOrder
     * @param array<string>|null $configuratorGroupConfigOrder
     *
     * @return array<int, string|null>
     */
    private function getOrder(?array $groupPositionOrder = null, ?array $configuratorGroupConfigOrder = null): array
    {
        // create product with property groups and 1 variant and get its configurator settings
        $productId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $groupIds = [
            'a' => Uuid::randomHex(),
            'b' => Uuid::randomHex(),
            'c' => Uuid::randomHex(),
            'd' => Uuid::randomHex(),
            'e' => Uuid::randomHex(),
            'f' => Uuid::randomHex(),
        ];

        $optionIds = [];

        $this->shuffle($groupIds);

        $configuratorSettings = [];
        foreach ($groupIds as $groupName => $groupId) {
            $group = [
                'id' => $groupId,
                'name' => $groupName,
            ];

            if ($groupPositionOrder) {
                $group['position'] = array_search($groupName, $groupPositionOrder, true);
            }

            // 2 options for each group
            $optionIds[$groupId] = [];
            for ($i = 0; $i < 2; ++$i) {
                $id = Uuid::randomHex();
                $optionIds[$groupId][] = $id;
                $configuratorSettings[] = [
                    'option' => [
                        'id' => $id,
                        'name' => $groupName . $i,
                        'group' => $group,
                    ],
                ];
            }
        }

        $configuratorGroupConfig = null;
        if ($configuratorGroupConfigOrder) {
            $configuratorGroupConfig = [];
            foreach ($configuratorGroupConfigOrder as $groupName) {
                $configuratorGroupConfig[] = [
                    'expressionForListings' => false,
                    'id' => $groupIds[$groupName],
                    'representation' => 'box',
                ];
            }
        }

        $data = [
            [
                'id' => $productId,
                'name' => 'Test product',
                'productNumber' => 'a.0',
                'manufacturer' => ['name' => 'test'],
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'test'],
                'stock' => 10,
                'active' => true,
                'type' => ProductDefinition::TYPE_PHYSICAL,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true]],
                'configuratorSettings' => $configuratorSettings,
                'variantListingConfig' => [
                    'configuratorGroupConfig' => $configuratorGroupConfig,
                ],
                'visibilities' => [
                    [
                        'salesChannelId' => TestDefaults::SALES_CHANNEL,
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
            [
                'id' => $variantId,
                'productNumber' => 'variant',
                'stock' => 10,
                'active' => true,
                'parentId' => $productId,
                'options' => array_map(static fn (array $group) => ['id' => $group[0]], $optionIds),
            ],
        ];

        $this->repository->create($data, Context::createDefaultContext());
        $this->addTaxDataToSalesChannel($this->context, $data[0]['tax']);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('product.parentId', $productId));
        /** @var SalesChannelProductEntity $salesChannelProduct */
        $salesChannelProduct = $this->salesChannelProductRepository->search($criteria, $this->context)->getEntities()->first();

        // get ordered PropertyGroupCollection
        $groups = $this->loader->load($salesChannelProduct, $this->context);
        $propertyGroupNames = array_map(static fn (PropertyGroupEntity $propertyGroupEntity) => $propertyGroupEntity->getName(), $groups->getElements());

        return array_values($propertyGroupNames);
    }

    /**
     * @return array{
     *     option: array{
     *         id: string,
     *         name: string,
     *         group: array{id: string, name: string, position: int}
     *     }
     * }
     */
    private function createConfiguratorSetting(
        string $optionId,
        string $optionName,
        string $groupId,
        string $groupName,
        int $groupPosition
    ): array {
        return [
            'option' => [
                'id' => $optionId,
                'name' => $optionName,
                'group' => [
                    'id' => $groupId,
                    'name' => $groupName,
                    'position' => $groupPosition,
                ],
            ],
        ];
    }
}
