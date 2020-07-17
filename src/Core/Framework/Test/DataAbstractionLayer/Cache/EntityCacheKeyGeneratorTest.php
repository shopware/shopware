<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxEntity;

class EntityCacheKeyGeneratorTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new EntityCacheKeyGenerator(
            $this->getContainer()->getParameter('kernel.cache.hash')
        );
    }

    public function testGenerateAssociationCacheTags(): void
    {
        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $product = new ProductEntity();
        $product->assign([
            'id' => $id,
            'name' => 'test',
            '_uniqueIdentifier' => $id,
            'tax' => (new TaxEntity())->assign([
                'id' => $id,
                '_uniqueIdentifier' => $id,
                'name' => 'test',
                'taxRate' => 15,
            ]),
            'prices' => new ProductPriceCollection([
                (new ProductPriceEntity())->assign([
                    'id' => $id,
                    '_uniqueIdentifier' => $id,
                ]),
                (new ProductPriceEntity())->assign([
                    'id' => $id2,
                    '_uniqueIdentifier' => $id2,
                ]),
            ]),
            'categories' => new CategoryCollection([
                (new CategoryEntity())->assign([
                    'id' => $id,
                    '_uniqueIdentifier' => $id,
                ]),
                (new CategoryEntity())->assign([
                    'id' => $id2,
                    '_uniqueIdentifier' => $id2,
                    'children' => new CategoryCollection([
                        (new CategoryEntity())->assign([
                            'id' => $id2,
                            '_uniqueIdentifier' => $id2,
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $tags = $this->generator->getAssociatedTags($this->getContainer()->get(ProductDefinition::class), $product, $context);

        static::assertContains('tax-' . $id, $tags);

        static::assertContains('product_price-' . $id, $tags);
        static::assertContains('product_price-' . $id2, $tags);

        static::assertContains('category-' . $id, $tags);
        static::assertContains('category-' . $id2, $tags);
    }

    public function testGenerateSearchCacheTags(): void
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('product.name'));
        $criteria->addSorting(new FieldSorting('product.manufacturer.name'));
        $criteria->addSorting(new FieldSorting('product.categories.name'));
        $criteria->addSorting(new FieldSorting('product.categories.media.title'));

        $tags = $this->generator->getSearchTags($this->getContainer()->get(ProductDefinition::class), $criteria);

        static::assertContains('product.id', $tags);
        static::assertContains('product_translation.name', $tags);
        static::assertContains('product.product_manufacturer_id', $tags);
        static::assertContains('product_manufacturer_translation.name', $tags);
        static::assertContains('product_category.category_id', $tags);
        static::assertContains('category_translation.name', $tags);
        static::assertContains('category.media_id', $tags);
        static::assertContains('media_translation.title', $tags);

        static::assertCount(9, $tags, print_r($tags, true));
    }

    public function testGenerateSearchCacheTagsWithoutPrefix(): void
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('name'));
        $criteria->addSorting(new FieldSorting('manufacturer.name'));
        $criteria->addSorting(new FieldSorting('categories.name'));
        $criteria->addSorting(new FieldSorting('categories.media.title'));

        $tags = $this->generator->getSearchTags($this->getContainer()->get(ProductDefinition::class), $criteria);

        static::assertContains('product.id', $tags);
        static::assertContains('product_translation.name', $tags);
        static::assertContains('product.product_manufacturer_id', $tags);
        static::assertContains('product_manufacturer_translation.name', $tags);
        static::assertContains('product_category.category_id', $tags);
        static::assertContains('category_translation.name', $tags);
        static::assertContains('category.media_id', $tags);
        static::assertContains('media_translation.title', $tags);

        static::assertCount(9, $tags, print_r($tags, true));
    }
}
