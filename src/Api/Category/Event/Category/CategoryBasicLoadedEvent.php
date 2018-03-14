<?php declare(strict_types=1);

namespace Shopware\Api\Category\Event\Category;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Media\Event\Media\MediaBasicLoadedEvent;
use Shopware\Api\Product\Event\ProductStream\ProductStreamBasicLoadedEvent;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CategoryBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'category.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var CategoryBasicCollection
     */
    protected $categories;

    public function __construct(CategoryBasicCollection $categories, ShopContext $context)
    {
        $this->context = $context;
        $this->categories = $categories;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
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
