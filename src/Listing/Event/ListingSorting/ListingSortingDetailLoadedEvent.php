<?php declare(strict_types=1);

namespace Shopware\Listing\Event\ListingSorting;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Listing\Collection\ListingSortingDetailCollection;
use Shopware\Listing\Event\ListingSortingTranslation\ListingSortingTranslationBasicLoadedEvent;
use Shopware\Product\Event\ProductStream\ProductStreamBasicLoadedEvent;

class ListingSortingDetailLoadedEvent extends NestedEvent
{
    const NAME = 'listing_sorting.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ListingSortingDetailCollection
     */
    protected $listingSortings;

    public function __construct(ListingSortingDetailCollection $listingSortings, TranslationContext $context)
    {
        $this->context = $context;
        $this->listingSortings = $listingSortings;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
        if ($this->listingSortings->getProductStreams()->count() > 0) {
            $events[] = new ProductStreamBasicLoadedEvent($this->listingSortings->getProductStreams(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
