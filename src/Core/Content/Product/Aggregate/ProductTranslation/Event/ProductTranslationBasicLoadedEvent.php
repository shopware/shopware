<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductTranslation\Event;

use Shopware\Core\Content\Product\Aggregate\ProductTranslation\Collection\ProductTranslationBasicCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class ProductTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_translation.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ProductTranslationBasicCollection
     */
    protected $productTranslations;

    public function __construct(ProductTranslationBasicCollection $productTranslations, Context $context)
    {
        $this->context = $context;
        $this->productTranslations = $productTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getProductTranslations(): ProductTranslationBasicCollection
    {
        return $this->productTranslations;
    }
}
