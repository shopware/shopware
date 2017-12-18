<?php declare(strict_types=1);

namespace Shopware\Api\Category\Event\Category;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Media\Event\Media\MediaBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CategoryBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'category.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CategoryBasicCollection
     */
    protected $categories;

    public function __construct(CategoryBasicCollection $categories, TranslationContext $context)
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

    public function getCategories(): CategoryBasicCollection
    {
        return $this->categories;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->categories->getMedia()->count() > 0) {
            $events[] = new MediaBasicLoadedEvent($this->categories->getMedia(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
