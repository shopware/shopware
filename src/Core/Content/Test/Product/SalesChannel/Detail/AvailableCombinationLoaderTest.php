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

class AvailableCombinationLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var AvailableCombinationLoader|null
     */
    private $loader;

    /**
     * @var EntityRepositoryInterface|null
     */
    private $productRepository;

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
                'options' => array_map(static function (array $group) {
                    // Assign first option from each group
                    return ['id' => $group[0]];
                }, $optionIds),
            ],
        ];

        $this->productRepository->create($data, $context);

        return $productId;
    }
}
