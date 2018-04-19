<?php declare(strict_types=1);

namespace Shopware\Api\Category\Event\Category;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CategoryBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'category.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CategoryBasicCollection
     */
    protected $categories;

    public function __construct(CategoryBasicCollection $categories, ApplicationContext $context)
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
