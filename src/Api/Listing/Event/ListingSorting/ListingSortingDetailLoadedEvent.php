<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Event\ListingSorting;

use Shopware\Api\Listing\Collection\ListingSortingDetailCollection;
use Shopware\Api\Listing\Event\ListingSortingTranslation\ListingSortingTranslationBasicLoadedEvent;
use Shopware\Api\Product\Event\ProductStream\ProductStreamBasicLoadedEvent;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ListingSortingDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_sorting.detail.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ListingSortingDetailCollection
     */
    protected $listingSortings;

    public function __construct(ListingSortingDetailCollection $listingSortings, ShopContext $context)
    {
        $this->context = $context;
        $this->listingSortings = $listingSortings;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getListingSortings(): ListingSortingDetailCollection
    {
        return $this->listingSortings;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->listingSortings->getTranslations()->count() > 0) {
            $events[] = new ListingSortingTranslationBasicLoadedEvent($this->listingSortings->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
