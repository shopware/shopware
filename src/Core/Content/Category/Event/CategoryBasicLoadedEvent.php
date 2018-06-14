<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Content\Category\Collection\CategoryBasicCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class CategoryBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'category.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var CategoryBasicCollection
     */
    protected $categories;

    public function __construct(CategoryBasicCollection $categories, Context $context)
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

    public function getCategories(): CategoryBasicCollection
    {
        return $this->categories;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];

        return new NestedEventCollection($events);
    }
}
