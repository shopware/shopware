<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductCategoryTree;

use Shopware\Api\Product\Collection\ProductCategoryTreeBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ProductCategoryTreeBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_category_tree.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ProductCategoryTreeBasicCollection
     */
    protected $productCategoryTrees;

    public function __construct(ProductCategoryTreeBasicCollection $productCategoryTrees, TranslationContext $context)
    {
        $this->context = $context;
        $this->productCategoryTrees = $productCategoryTrees;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getProductCategoryTrees(): ProductCategoryTreeBasicCollection
    {
        return $this->productCategoryTrees;
    }
}
