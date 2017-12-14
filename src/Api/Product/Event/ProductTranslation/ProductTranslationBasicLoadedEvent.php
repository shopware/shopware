<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductTranslation;

use Shopware\Api\Product\Collection\ProductTranslationBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

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
