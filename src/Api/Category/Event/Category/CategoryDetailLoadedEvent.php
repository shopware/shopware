<?php declare(strict_types=1);

namespace Shopware\Api\Category\Event\Category;

use Shopware\Api\Category\Collection\CategoryDetailCollection;
use Shopware\Api\Category\Event\CategoryTranslation\CategoryTranslationBasicLoadedEvent;
use Shopware\Api\Media\Event\Media\MediaBasicLoadedEvent;
use Shopware\Api\Product\Event\Product\ProductBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CategoryDetailLoadedEvent extends NestedEvent
{
    const NAME = 'category.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CategoryDetailCollection
     */
    protected $categories;

    public function __construct(CategoryDetailCollection $categories, TranslationContext $context)
    {
        $this->context = $context;
        $this->categories = $categories;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCategories(): CategoryDetailCollection
    {
        return $this->categories;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->categories->getParents()->count() > 0) {
            $events[] = new CategoryBasicLoadedEvent($this->categories->getParents(), $this->context);
        }
        if ($this->categories->getMedia()->count() > 0) {
            $events[] = new MediaBasicLoadedEvent($this->categories->getMedia(), $this->context);
        }
        if ($this->categories->getTranslations()->count() > 0) {
            $events[] = new CategoryTranslationBasicLoadedEvent($this->categories->getTranslations(), $this->context);
        }
        if ($this->categories->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->categories->getShops(), $this->context);
        }
        if ($this->categories->getAllProducts()->count() > 0) {
            $events[] = new ProductBasicLoadedEvent($this->categories->getAllProducts(), $this->context);
        }
        if ($this->categories->getAllProductTree()->count() > 0) {
            $events[] = new ProductBasicLoadedEvent($this->categories->getAllProductTree(), $this->context);
        }
        if ($this->categories->getAllSeoProducts()->count() > 0) {
            $events[] = new ProductBasicLoadedEvent($this->categories->getAllSeoProducts(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
