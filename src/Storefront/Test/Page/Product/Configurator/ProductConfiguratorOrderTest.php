<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Product\Configurator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Product\Configurator\ProductCombinationFinder;
use Shopware\Storefront\Page\Product\Configurator\ProductPageConfiguratorLoader;

class ProductConfiguratorOrderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $salesChannelProductRepository;

    /**
     * @var string
     */
    private $productId;

    /**
     * @var SalesChannelContext
     */
    private $context;

    /**
     * @var ProductCombinationFinder
     */
    private $combinationFinder;

    /**
     * @var ProductPageConfiguratorLoader
     */
    private $productPageConfiguratorLoader;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product.repository');

        $this->salesChannelProductRepository = $this->getContainer()->get('sales_channel.product.repository');

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create('test', Defaults::SALES_CHANNEL);

        $this->combinationFinder = $this->getContainer()->get(ProductCombinationFinder::class);

        $this->productPageConfiguratorLoader = $this->getContainer()->get(ProductPageConfiguratorLoader::class);

        parent::setUp();
    }

    public function testDefaultOrder(): void
    {
        $groupNames = $this->getOrder();
        static::assertEquals(['a', 'b', 'c', 'd', 'e', 'f'], $groupNames);
    }

    public function testGroupPositionOrder(): void
    {
        $groupNames = $this->getOrder(['f', 'e', 'd', 'c', 'b', 'a']);
        static::assertEquals(['f', 'e', 'd', 'c', 'b', 'a'], $groupNames);
    }

    public function testConfiguratorGroupConfigOrder(): void
    {
        $groupNames = $this->getOrder(null, ['f', 'e', 'd', 'c', 'b', 'a']);
        static::assertEquals(['f', 'e', 'd', 'c', 'b', 'a'], $groupNames);
    }

    public function testConfiguratorGroupConfigOverrideOrder(): void
    {
        $groupNames = $this->getOrder(['f', 'b', 'c', 'd', 'a', 'e'], ['f', 'e', 'd', 'c', 'b', 'a']);
        static::assertEquals(['f', 'e', 'd', 'c', 'b', 'a'], $groupNames);
    }

    private static function ashuffle(array &$a)
    {
        $keys = array_keys($a);
        shuffle($keys);
        $shuffled = [];
        foreach ($keys as $key) {
            $shuffled[$key] = $a[$key];
        }
        $a = $shuffled;

        return true;
    }

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

        self::ashuffle($groupIds);

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
                'tax' => ['id' => UUid::randomHex(), 'taxRate' => 19, 'name' => 'test'],
                'stock' => 10,
                'active' => true,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true]],
                'configuratorSettings' => $configuratorSettings,
                'configuratorGroupConfig' => $configuratorGroupConfig,
                'visibilities' => [
                    [
                        'salesChannelId' => Defaults::SALES_CHANNEL,
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
                'options' => array_map(function (array $group) {
                    // Assign first option from each group
                    return ['id' => $group[0]];
                }, $optionIds),
            ],
        ];

        $this->repository->create($data, Context::createDefaultContext());
        $this->addTaxDataToSalesChannel($this->context, $data[0]['tax']);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('product.parentId', $productId));
        $salesChannelProduct = $this->salesChannelProductRepository->search($criteria, $this->context)->first();

        // get ordered PropertyGroupCollection
        $groups = $this->productPageConfiguratorLoader->load($salesChannelProduct, $this->context);

        // return array of group names
        return array_values(array_map(function (PropertyGroupEntity $propertyGroupEntity) {
            return $propertyGroupEntity->getName();
        }, $groups->getElements()));
    }
}
