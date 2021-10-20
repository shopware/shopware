<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\Detail;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

class AvailableCombinationLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AvailableCombinationLoader $loader;

    private EntityRepositoryInterface $productRepository;

    protected function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->loader = $this->getContainer()->get(AvailableCombinationLoader::class);
    }

    public function testCombinationsAreInResult(): void
    {
        $context = Context::createDefaultContext();
        $productId = $this->createProduct($context);
        $result = $this->loader->load($productId, $context);

        foreach ($result->getCombinations() as $combinationHash => $combination) {
            static::assertTrue($result->hasCombination($combination));

            foreach ($combination as $optionId) {
                static::assertTrue($result->hasOptionId($optionId));
            }

            static::assertTrue(\in_array($combinationHash, $result->getHashes(), true));
        }
    }

    /**
     * @dataProvider availabilityProvider
     */
    public function testCombinationAvailability(int $stock, bool $expected, ?bool $parentCloseout, ?bool $isCloseout): void
    {
        $context = Context::createDefaultContext();
        $productId = $this->createProduct(
            $context,
            [
                'isCloseout' => $parentCloseout,
            ],
            [
                'isCloseout' => $isCloseout,
                'stock' => $stock,
            ]
        );

        $result = $this->loader->load($productId, $context);

        foreach ($result->getCombinations() as $combination) {
            static::assertEquals($expected, $result->isAvailable($combination));
        }
    }

    public function availabilityProvider(): iterable
    {
        yield 'test parentCloseout = true and isCloseout = true and stock = 0' => [0, false, true, true];
        yield 'test parentCloseout = true and isCloseout = false and stock = 0' => [0, true, true, false];
        yield 'test parentCloseout = true and isCloseout = null and stock = 0' => [0, false, true, null];

        yield 'test parentCloseout = false and isCloseout = true and stock = 0' => [0, false, false, true];
        yield 'test parentCloseout = false and isCloseout = false and stock = 0' => [0, true, false, false];
        yield 'test parentCloseout = false and isCloseout = null and stock = 0' => [0, true, false, null];

        yield 'test parentCloseout = null and isCloseout = true and stock = 0' => [0, false, null, true];
        yield 'test parentCloseout = null and isCloseout = false and stock = 0' => [0, true, null, false];
        yield 'test parentCloseout = null and isCloseout = null and stock = 0' => [0, true, null, null];

        yield 'test parentCloseout = true and isCloseout = true and stock = 1' => [1, true, true, true];
        yield 'test parentCloseout = true and isCloseout = false and stock = 1' => [1, true, true, false];
        yield 'test parentCloseout = true and isCloseout = null and stock = 1' => [1, true, true, null];

        yield 'test parentCloseout = false and isCloseout = true and stock = 1' => [1, true, false, true];
        yield 'test parentCloseout = false and isCloseout = false and stock = 1' => [1, true, false, false];
        yield 'test parentCloseout = false and isCloseout = null and stock = 1' => [1, true, false, null];

        yield 'test parentCloseout = null and isCloseout = true and stock = 1' => [1, true, null, true];
        yield 'test parentCloseout = null and isCloseout = false and stock = 1' => [1, true, null, false];
        yield 'test parentCloseout = null and isCloseout = null and stock = 1' => [1, true, null, null];
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

    private function createProduct(
        Context $context,
        array $productOverrides = [],
        array $variantOverrides = []
    ): string {
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
            'tax' => ['id' => UUid::randomHex(), 'taxRate' => 19, 'name' => 'test'],
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

        $product = \array_replace_recursive($product, $productOverrides);

        $variant = [
            'id' => $variantId,
            'productNumber' => 'variant',
            'stock' => 10,
            'active' => true,
            'parentId' => $productId,
            'options' => array_map(static function (array $group) {
                // Assign first option from each group
                return ['id' => $group[0]];
            }, $optionIds),
        ];

        $variant = \array_replace_recursive($variant, $variantOverrides);

        $this->productRepository->create([$product, $variant], $context);

        return $productId;
    }
}
