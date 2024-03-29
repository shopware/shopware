<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Detail;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class AvailableCombinationLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private AbstractAvailableCombinationLoader $loader;

    private EntityRepository $productRepository;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->loader = $this->getContainer()->get(AvailableCombinationLoader::class);
        $this->ids = new TestDataCollection();

        $this->createSalesChannel([
            'id' => $this->ids->get('sales-channel'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://test.to',
                ],
            ],
        ]);
    }

    public function testCombinationsAreInResult(): void
    {
        $context = Context::createDefaultContext();
        $salesChanelContext = Generator::createSalesChannelContext($context);
        $productId = $this->createProduct($context);
        $result = $this->loader->loadCombinations($productId, $salesChanelContext);

        foreach ($result->getCombinations() as $combinationHash => $combination) {
            static::assertTrue($result->hasCombination($combination));

            foreach ($combination as $optionId) {
                static::assertTrue($result->hasOptionId($optionId));
            }

            static::assertTrue(\in_array($combinationHash, $result->getHashes(), true));
        }
    }

    #[DataProvider('availabilityProvider')]
    public function testCombinationAvailability(
        int $stock,
        bool $expected,
        ?bool $parentCloseout,
        ?bool $isCloseout,
        int $minPurchase,
        bool $differentChannel = false
    ): void {
        $products = (new ProductBuilder($this->ids, 'a.0'))
            ->manufacturer('m1')
            ->name('test')
            ->price(10)
            ->visibility(TestDefaults::SALES_CHANNEL)
            ->configuratorSetting('red', 'color')
            ->configuratorSetting('xl', 'size')
            ->stock(10)
            ->closeout($parentCloseout)
            ->variant(
                (new ProductBuilder($this->ids, 'a.1'))
                    ->visibility($differentChannel ? $this->ids->get('sales-channel') : TestDefaults::SALES_CHANNEL)
                    ->option('red', 'color')
                    ->option('xl', 'size')
                    ->stock($stock)
                    ->closeout($isCloseout)
                    ->add('minPurchase', $minPurchase)
                    ->build()
            )
            ->build();

        $this->getContainer()->get('product.repository')->create([$products], Context::createDefaultContext());

        $context = Context::createDefaultContext();
        $salesChanelContext = Generator::createSalesChannelContext($context);
        $result = $this->loader->loadCombinations($this->ids->get('a.0'), $salesChanelContext);

        foreach ($result->getCombinations() as $combination) {
            static::assertEquals($expected, $result->isAvailable($combination));
        }
    }

    /**
     * @return \Generator<string, array{0:int, 1:bool, 2:bool|null, 3:bool|null, 4:int, 5?:bool}>
     */
    public static function availabilityProvider(): \Generator
    {
        yield 'test parentCloseout = true and isCloseout = true and stock = 0 and minPurchase = 1' => [0, false, true, true, 1];
        yield 'test parentCloseout = true and isCloseout = false and stock = 0 and minPurchase = 1' => [0, true, true, false, 1];
        yield 'test parentCloseout = true and isCloseout = null and stock = 0 and minPurchase = 1' => [0, false, true, null, 1];

        yield 'test parentCloseout = false and isCloseout = true and stock = 0 and minPurchase = 1' => [0, false, false, true, 1];
        yield 'test parentCloseout = false and isCloseout = false and stock = 0 and minPurchase = 1' => [0, true, false, false, 1];
        yield 'test parentCloseout = false and isCloseout = null and stock = 0 and minPurchase = 1' => [0, true, false, null, 1];

        yield 'test parentCloseout = null and isCloseout = true and stock = 0 and minPurchase = 1' => [0, false, null, true, 1];
        yield 'test parentCloseout = null and isCloseout = false and stock = 0 and minPurchase = 1' => [0, true, null, false, 1];
        yield 'test parentCloseout = null and isCloseout = null and stock = 0 and minPurchase = 1' => [0, true, null, null, 1];

        yield 'test parentCloseout = true and isCloseout = true and stock = 1 and minPurchase = 1' => [1, true, true, true, 1];
        yield 'test parentCloseout = true and isCloseout = false and stock = 1 and minPurchase = 1' => [1, true, true, false, 1];
        yield 'test parentCloseout = true and isCloseout = null and stock = 1 and minPurchase = 1' => [1, true, true, null, 1];

        yield 'test parentCloseout = true and isCloseout = true and stock = 1 and minPurchase = 2' => [1, false, true, true, 2];
        yield 'test parentCloseout = true and isCloseout = false and stock = 1 and minPurchase = 2' => [1, true, true, false, 2];
        yield 'test parentCloseout = true and isCloseout = null and stock = 1 and minPurchase = 2' => [1, false, true, null, 2];

        yield 'test parentCloseout = false and isCloseout = true and stock = 1 and minPurchase = 1' => [1, true, false, true, 1];
        yield 'test parentCloseout = false and isCloseout = false and stock = 1 and minPurchase = 1' => [1, true, false, false, 1];
        yield 'test parentCloseout = false and isCloseout = null and stock = 1 and minPurchase = 1' => [1, true, false, null, 1];

        yield 'test parentCloseout = false and isCloseout = true and stock = 1 and minPurchase = 2' => [1, false, false, true, 2];
        yield 'test parentCloseout = false and isCloseout = false and stock = 1 and minPurchase = 2' => [1, true, false, false, 2];
        yield 'test parentCloseout = false and isCloseout = null and stock = 1 and minPurchase = 2' => [1, true, false, null, 2];

        yield 'test parentCloseout = null and isCloseout = true and stock = 1 and minPurchase = 1' => [1, true, null, true, 1];
        yield 'test parentCloseout = null and isCloseout = false and stock = 1 and minPurchase = 1' => [1, true, null, false, 1];
        yield 'test parentCloseout = null and isCloseout = null and stock = 1 and minPurchase = 1' => [1, true, null, null, 1];

        yield 'test parentCloseout = null and isCloseout = true and stock = 1 and minPurchase = 2' => [1, false, null, true, 2];
        yield 'test parentCloseout = null and isCloseout = false and stock = 1 and minPurchase = 2' => [1, true, null, false, 2];
        yield 'test parentCloseout = null and isCloseout = null and stock = 1 and minPurchase = 2' => [1, true, null, null, 2];

        yield 'test parentCloseout = true and isCloseout = true and stock = 1 and minPurchase = 1 and differentChannel = true' => [1, false, null, true, 1, true];
        yield 'test parentCloseout = true and isCloseout = false and stock = 1 and minPurchase = 1 and differentChannel = true' => [1, false, null, false, 1, true];
        yield 'test parentCloseout = true and isCloseout = null and stock = 1 and minPurchase = 1 and differentChannel = true' => [1, false, null, null, 1, true];
    }

    /**
     * @param array<mixed> $a
     */
    private function ashuffle(array &$a): void
    {
        $keys = array_keys($a);
        shuffle($keys);
        $shuffled = [];
        foreach ($keys as $key) {
            $shuffled[$key] = $a[$key];
        }
        $a = $shuffled;
    }

    private function createProduct(Context $context): string
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

        $this->ashuffle($groupIds);

        $configuratorSettings = [];
        foreach ($groupIds as $groupName => $groupId) {
            $group = [
                'id' => $groupId,
                'name' => $groupName,
            ];

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

        $product = [
            'id' => $productId,
            'name' => 'Test product',
            'productNumber' => 'a.0',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'test'],
            'stock' => 10,
            'active' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true]],
            'configuratorSettings' => $configuratorSettings,
            'configuratorGroupConfig' => $configuratorGroupConfig,
            'visibilities' => [
                [
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $variant = [
            'id' => $variantId,
            'productNumber' => 'variant',
            'stock' => 10,
            'active' => true,
            'parentId' => $productId,
            'options' => array_map(static fn (array $group) => ['id' => $group[0]], $optionIds),
        ];

        $this->productRepository->create([$product, $variant], $context);

        return $productId;
    }
}
