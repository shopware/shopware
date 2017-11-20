<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Product\Collection\ProductTranslationDetailCollection;
use Shopware\Product\Event\Product\ProductBasicLoadedEvent;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;

class ProductTranslationDetailLoadedEvent extends NestedEvent
{
    const NAME = 'product_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ProductTranslationDetailCollection
     */
    protected $productTranslations;

    public function __construct(ProductTranslationDetailCollection $productTranslations, TranslationContext $context)
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

    public function getProductTranslations(): ProductTranslationDetailCollection
    {
        return $this->productTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productTranslations->getProducts()->count() > 0) {
            $events[] = new ProductBasicLoadedEvent($this->productTranslations->getProducts(), $this->context);
        }
        if ($this->productTranslations->getLanguages()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->productTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
