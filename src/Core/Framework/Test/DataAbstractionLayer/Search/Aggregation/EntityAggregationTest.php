<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxDefinition;

class EntityAggregationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const TAX_NO_1 = '061af626d7714bd6ad4cad3598a2c716';
    private const TAX_NO_2 = 'ceac25750cdb4415b6a324fd6b857731';
    private const TAX_NO_3 = 'f97b4c864b7042f681d9e78ee644207b';
    private const TAX_NO_4 = '395a0ae58397416ca7a4bcb4d6324576';
    private const TAX_NO_5 = '8e96eabfd9a0446099a651eb2fd1d231';
    private const TAX_NO_6 = 'd281b2a352234db0b851d962c6b3ba88';
    private const TAX_NO_7 = 'c8389a17dda5420caf0bd4f46e89b163';
    private const TAX_NO_8 = 'dbae0258ea0f4e90bcbcb6fe6d9d0f08';

    /**
     * @var EntityRepositoryInterface
     */
    private $taxRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    protected function setUp(): void
    {
        $this->taxRepository = $this->getContainer()->get('tax.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->categoryRepository = $this->getContainer()->get('category.repository');
    }

    public function testEntityAggregation(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $ids['categories']));
        $criteria->addAggregation(new EntityAggregation('taxId', TaxDefinition::class, 'tax_count'));

        $entityAgg = $this->productRepository->aggregate($criteria, $context)->getAggregations()->get('tax_count');
        static::assertNotNull($entityAgg);
        /** @var EntityAggregation $entityAggregation */
        $entityAggregation = $entityAgg->getAggregation();
        static::assertSame(TaxDefinition::class, $entityAggregation->getDefinition());
        static::assertCount(1, $entityAgg->getResult());

        /** @var EntityResult $firstEntityAgg */
        $firstEntityAgg = $entityAgg->getResult()[0];
        $entities = $firstEntityAgg->getEntities();
        static::assertSame(4, $entities->count());
        static::assertTrue($entities->has(self::TAX_NO_1));
        static::assertTrue($entities->has(self::TAX_NO_2));
        static::assertTrue($entities->has(self::TAX_NO_5));
        static::assertTrue($entities->has(self::TAX_NO_6));
    }

    public function testEntityAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $ids['categories']));
        $criteria->addAggregation(new EntityAggregation('taxId', TaxDefinition::class, 'tax_count', 'product.categories.name'));

        /** @var AggregationResult $entityAgg */
        $entityAgg = $this->productRepository->aggregate($criteria, $context)->getAggregations()->get('tax_count');
        static::assertNotNull($entityAgg);
        /** @var EntityAggregation $entityAggregation */
        $entityAggregation = $entityAgg->getAggregation();
        static::assertSame(TaxDefinition::class, $entityAggregation->getDefinition());
        static::assertCount(4, $entityAgg->getResult());

        /** @var EntityResult $entityAggCat1 */
        $entityAggCat1 = $entityAgg->get(['product.categories.name' => 'cat1']);
        $entities = $entityAggCat1->getEntities();
        static::assertSame(3, $entities->count());
        static::assertTrue($entities->has(self::TAX_NO_1));
        static::assertTrue($entities->has(self::TAX_NO_5));
        static::assertTrue($entities->has(self::TAX_NO_6));

        /** @var EntityResult $entityAggCat2 */
        $entityAggCat2 = $entityAgg->get(['product.categories.name' => 'cat2']);
        $entities = $entityAggCat2->getEntities();
        static::assertSame(3, $entities->count());
        static::assertTrue($entities->has(self::TAX_NO_1));
        static::assertTrue($entities->has(self::TAX_NO_5));
        static::assertTrue($entities->has(self::TAX_NO_6));

        /** @var EntityResult $entityAggCat3 */
        $entityAggCat3 = $entityAgg->get(['product.categories.name' => 'cat3']);
        $entities = $entityAggCat3->getEntities();
        static::assertSame(4, $entities->count());
        static::assertTrue($entities->has(self::TAX_NO_1));
        static::assertTrue($entities->has(self::TAX_NO_2));
        static::assertTrue($entities->has(self::TAX_NO_5));
        static::assertTrue($entities->has(self::TAX_NO_6));

        /** @var EntityResult $entityAggCat4 */
        $entityAggCat4 = $entityAgg->get(['product.categories.name' => 'cat4']);
        $entities = $entityAggCat4->getEntities();
        static::assertSame(2, $entities->count());
        static::assertTrue($entities->has(self::TAX_NO_5));
        static::assertTrue($entities->has(self::TAX_NO_6));
    }

    private function setupFixtures(Context $context): array
    {
        $category1 = Uuid::randomHex();
        $category2 = Uuid::randomHex();
        $category3 = Uuid::randomHex();
        $category4 = Uuid::randomHex();
        $categories = [
            ['id' => $category1, 'name' => 'cat1'],
            ['id' => $category2, 'name' => 'cat2'],
            ['id' => $category3, 'name' => 'cat3'],
            ['id' => $category4, 'name' => 'cat4'],
        ];
        $this->categoryRepository->create($categories, $context);

        $payload = [
            ['name' => 'Tax rate #1', 'taxRate' => 10, 'id' => self::TAX_NO_1],
            ['name' => 'Tax rate #2', 'taxRate' => 20, 'id' => self::TAX_NO_2],
            ['name' => 'Tax rate #3', 'taxRate' => 10, 'id' => self::TAX_NO_3],
            ['name' => 'Tax rate #4', 'taxRate' => 20, 'id' => self::TAX_NO_4],
            ['name' => 'Tax rate #5', 'taxRate' => 50, 'id' => self::TAX_NO_5],
            ['name' => 'Tax rate #6', 'taxRate' => 50, 'id' => self::TAX_NO_6],
            ['name' => 'Tax rate #7', 'taxRate' => 90, 'id' => self::TAX_NO_7],
            ['name' => 'Tax rate #8', 'taxRate' => 10, 'id' => self::TAX_NO_8],
        ];

        $this->taxRepository->create($payload, $context);

        $manufacturer = [
            'id' => Uuid::randomHex(),
            'name' => 'shopware AG',
        ];

        $productPayload = [
            [
                'productNumber' => Uuid::randomHex(),
                'taxId' => $payload[0]['id'],
                'stock' => 1,
                'name' => 'Test product #1',
                'manufacturer' => $manufacturer,
                'price' => [['net' => 10, 'currencyId' => Defaults::CURRENCY, 'gross' => 20, 'linked' => false]],
                'categories' => [['id' => $category1], ['id' => $category2]],
            ],
            [
                'productNumber' => Uuid::randomHex(),
                'taxId' => $payload[0]['id'],
                'stock' => 1,
                'name' => 'Test product #2',
                'manufacturer' => $manufacturer,
                'price' => [['net' => 10, 'currencyId' => Defaults::CURRENCY, 'gross' => 20, 'linked' => false]],
                'categories' => [['id' => $category2], ['id' => $category3]],
            ],
            [
                'productNumber' => Uuid::randomHex(),
                'taxId' => $payload[1]['id'],
                'stock' => 1,
                'name' => 'Test product #3',
                'manufacturer' => $manufacturer,
                'price' => [['net' => 10, 'currencyId' => Defaults::CURRENCY, 'gross' => 20, 'linked' => false]],
                'categories' => [['id' => $category3]],
            ],
            [
                'productNumber' => Uuid::randomHex(),
                'taxId' => $payload[4]['id'],
                'stock' => 1,
                'name' => 'Test product #4',
                'manufacturer' => $manufacturer,
                'price' => [['net' => 10, 'currencyId' => Defaults::CURRENCY, 'gross' => 20, 'linked' => false]],
                'categories' => [['id' => $category1], ['id' => $category4]],
            ],
            [
                'productNumber' => Uuid::randomHex(),
                'taxId' => $payload[4]['id'],
                'stock' => 1,
                'name' => 'Test product #5',
                'manufacturer' => $manufacturer,
                'price' => [['net' => 10, 'currencyId' => Defaults::CURRENCY, 'gross' => 20, 'linked' => false]],
                'categories' => [['id' => $category2], ['id' => $category3]],
            ],
            [
                'productNumber' => Uuid::randomHex(),
                'taxId' => $payload[5]['id'],
                'stock' => 1,
                'name' => 'Test product #6',
                'manufacturer' => $manufacturer,
                'price' => [['net' => 10, 'currencyId' => Defaults::CURRENCY, 'gross' => 20, 'linked' => false]],
                'categories' => [['id' => $category4]],
            ],
            [
                'productNumber' => Uuid::randomHex(),
                'taxId' => $payload[5]['id'],
                'stock' => 1,
                'name' => 'Test product #7',
                'manufacturer' => $manufacturer,
                'price' => [['net' => 10, 'currencyId' => Defaults::CURRENCY, 'gross' => 20, 'linked' => false]],
                'categories' => [['id' => $category3], ['id' => $category4]],
            ],
            [
                'productNumber' => Uuid::randomHex(),
                'taxId' => $payload[5]['id'],
                'stock' => 1,
                'name' => 'Test product #8',
                'manufacturer' => $manufacturer,
                'price' => [['net' => 10, 'currencyId' => Defaults::CURRENCY, 'gross' => 20, 'linked' => false]],
                'categories' => [['id' => $category1], ['id' => $category2]],
            ],
            [
                'productNumber' => Uuid::randomHex(),
                'taxId' => $payload[5]['id'],
                'stock' => 1,
                'name' => 'Test product #9',
                'manufacturer' => $manufacturer,
                'price' => [['net' => 10, 'currencyId' => Defaults::CURRENCY, 'gross' => 20, 'linked' => false]],
                'categories' => [['id' => $category1], ['id' => $category3]],
            ],
        ];

        $this->productRepository->create($productPayload, $context);

        return [
            'categories' => array_column($categories, 'id'),
        ];
    }
}
