<?php declare(strict_types=1);

namespace Shopware\Content\Category\Event\Category;

use Shopware\Content\Category\Collection\CategoryDetailCollection;
use Shopware\Content\Category\Event\CategoryTranslation\CategoryTranslationBasicLoadedEvent;
use Shopware\Content\Media\Event\Media\MediaBasicLoadedEvent;
use Shopware\Content\Product\Event\ProductStream\ProductStreamBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CategoryDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'category.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CategoryDetailCollection
     */
    protected $categories;

    public function __construct(CategoryDetailCollection $categories, ApplicationContext $context)
    {
        $this->context = $context;
        $this->categories = $categories;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
