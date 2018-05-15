<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductTranslation;

use Shopware\Application\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\Content\Product\Collection\ProductTranslationDetailCollection;
use Shopware\Content\Product\Event\Product\ProductBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ProductTranslationDetailCollection
     */
    protected $productTranslations;

    public function __construct(ProductTranslationDetailCollection $productTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->productTranslations = $productTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
