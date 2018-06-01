<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\Collection\ProductTranslationDetailCollection;
use Shopware\Core\Content\Product\Event\ProductBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class ProductTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_translation.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var ProductTranslationDetailCollection
     */
    protected $productTranslations;

    public function __construct(ProductTranslationDetailCollection $productTranslations, Context $context)
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
            $events[] = new LanguageBasicLoadedEvent($this->productTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
