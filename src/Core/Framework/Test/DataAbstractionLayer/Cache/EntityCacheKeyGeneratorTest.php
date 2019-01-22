<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Tax\TaxEntity;

class EntityCacheKeyGeneratorTest extends TestCase
{
    /**
     * @var EntityCacheKeyGenerator
     */
    private $generator;

    protected function setUp()
    {
        parent::setUp();
        $this->generator = new EntityCacheKeyGenerator();
    }

    public function testGenerateAssociationCacheTags()
    {
        $context = Context::createDefaultContext();

        $id = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();
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
            'priceRules' => new ProductPriceRuleCollection([
                (new ProductPriceRuleEntity())->assign([
                    'id' => $id,
                    '_uniqueIdentifier' => $id,
                    'currency' => (new CurrencyEntity())->assign([
                        'id' => $id,
                        '_uniqueIdentifier' => $id,
                    ]),
                ]),
                (new ProductPriceRuleEntity())->assign([
                    'id' => $id2,
                    '_uniqueIdentifier' => $id2,
                    'currency' => (new CurrencyEntity())->assign([
                        'id' => $id2,
                        '_uniqueIdentifier' => $id2,
                    ]),
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

        $tags = $this->generator->getAssociatedTags(ProductDefinition::class, $product, $context);

        static::assertContains('product_translation.language_id', $tags, print_r($tags, true));
        static::assertContains('tax-' . $id, $tags);

        static::assertContains('product_price_rule-' . $id, $tags);
        static::assertContains('currency-' . $id, $tags);
        static::assertContains('currency_translation.language_id', $tags);

        static::assertContains('product_price_rule-' . $id2, $tags);
        static::assertContains('currency-' . $id2, $tags);
        static::assertContains('currency_translation.language_id', $tags);

        static::assertContains('category-' . $id, $tags);
        static::assertContains('category_translation.language_id', $tags);

        static::assertContains('category-' . $id2, $tags);
    }

    public function testGenerateSearchCacheTags()
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('product.name'));
        $criteria->addSorting(new FieldSorting('product.manufacturer.name'));
        $criteria->addSorting(new FieldSorting('product.categories.name'));
        $criteria->addSorting(new FieldSorting('product.categories.media.title'));

        $context = Context::createDefaultContext();

        $tags = $this->generator->getSearchTags(ProductDefinition::class, $criteria);

        static::assertCount(9, $tags, print_r($tags, true));
        static::assertContains('product.id', $tags);
        static::assertContains('product_translation.name', $tags);
        static::assertContains('product.product_manufacturer_id', $tags);
        static::assertContains('product_manufacturer_translation.name', $tags);
        static::assertContains('product_category.category_id', $tags);
        static::assertContains('category_translation.name', $tags);
        static::assertContains('category.media_id', $tags);
        static::assertContains('media_translation.title', $tags);
    }
}
