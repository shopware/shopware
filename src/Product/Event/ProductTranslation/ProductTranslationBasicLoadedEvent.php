<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Product\Collection\ProductTranslationBasicCollection;

class ProductTranslationBasicLoadedEvent extends NestedEvent
{
    const NAME = 'product_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ProductTranslationBasicCollection
     */
    protected $productTranslations;

    public function __construct(ProductTranslationBasicCollection $productTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->productTranslations = $productTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getProductTranslations(): ProductTranslationBasicCollection
    {
        return $this->productTranslations;
    }
}
