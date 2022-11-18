<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompositeEntitySearcher;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @deprecated tag:v6.5.0 - Will be removed
 */
class CompositeEntitySearcherTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $productRepository;

    private CompositeEntitySearcher $search;

    private Context $context;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->search = $this->getContainer()->get(CompositeEntitySearcher::class);

        $this->context = Context::createDefaultContext();
    }

    public function testDefinitionsAreUnique(): void
    {
        $ref = new \ReflectionClass($this->search);
        $property = $ref->getProperty('definitions');
        $property->setAccessible(true);
        $definitions = $property->getValue($this->search);

        $uniqueDefinitions = [];
        foreach ($definitions as $definition) {
            $uniqueDefinitions[$definition->getEntityName()] = $definition;
        }

        static::assertCount(\count($uniqueDefinitions), $definitions);
    }

    public function testProductRanking(): void
    {
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();

        $filterId = Uuid::randomHex();

        $this->productRepository->upsert([
            ['id' => $productId1, 'productNumber' => Uuid::randomHex(), 'stock' => 1, 'name' => "{$filterId}_test {$filterId}_product 1", 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test']],
            ['id' => $productId2, 'productNumber' => Uuid::randomHex(), 'stock' => 1, 'name' => "{$filterId}_test {$filterId}_product 2", 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test']],
            ['id' => Uuid::randomHex(), 'productNumber' => Uuid::randomHex(), 'stock' => 1, 'name' => 'notmatch', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'notmatch', 'taxRate' => 5], 'manufacturer' => ['name' => 'notmatch']],
            ['id' => Uuid::randomHex(), 'productNumber' => Uuid::randomHex(), 'stock' => 1, 'name' => 'notmatch', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'notmatch', 'taxRate' => 5], 'manufacturer' => ['name' => 'notmatch']],
        ], $this->context);

        $result = $this->search->search("{$filterId}_test {$filterId}_product", 20, $this->context);

        $productResult = current(array_filter($result, function ($item) {
            return $item['entity'] === $this->getContainer()->get(ProductDefinition::class)->getEntityName();
        }));

        static::assertNotEmpty($productResult);

        /** @var ProductCollection $products */
        $products = $productResult['entities']->getEntities();
        $first = $products->first();
        $last = $products->last();

        static::assertInstanceOf(ProductEntity::class, $first);
        static::assertInstanceOf(ProductEntity::class, $last);

        /** @var ArrayEntity $firstSearchExtension */
        $firstSearchExtension = $first->getExtension('search');
        $firstScore = $firstSearchExtension->get('_score');

        /** @var ArrayEntity $secondSearchExtension */
        $secondSearchExtension = $first->getExtension('search');
        $secondScore = $secondSearchExtension->get('_score');

        static::assertSame($secondScore, $firstScore);
    }
}
