<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\Event\CategoryTranslationBasicLoadedEvent;
use Shopware\Core\Content\Category\Collection\CategoryDetailCollection;
use Shopware\Core\Content\Media\Event\MediaBasicLoadedEvent;
use Shopware\Core\Content\Product\Aggregate\ProductStream\Event\ProductStreamBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class CategoryDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'category.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var CategoryDetailCollection
     */
    protected $categories;

    public function __construct(CategoryDetailCollection $categories, Context $context)
    {
        $this->context = $context;
        $this->categories = $categories;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
        if ($this->categories->getProductStreams()->count() > 0) {
            $events[] = new ProductStreamBasicLoadedEvent($this->categories->getProductStreams(), $this->context);
        }
        if ($this->categories->getChildren()->count() > 0) {
            $events[] = new CategoryBasicLoadedEvent($this->categories->getChildren(), $this->context);
        }
        if ($this->categories->getTranslations()->count() > 0) {
            $events[] = new CategoryTranslationBasicLoadedEvent($this->categories->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
