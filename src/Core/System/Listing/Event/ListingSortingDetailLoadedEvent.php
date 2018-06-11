<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\Event\ListingSortingTranslationBasicLoadedEvent;
use Shopware\Core\System\Listing\Collection\ListingSortingDetailCollection;

class ListingSortingDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_sorting.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ListingSortingDetailCollection
     */
    protected $listingSortings;

    public function __construct(ListingSortingDetailCollection $listingSortings, Context $context)
    {
        $this->context = $context;
        $this->listingSortings = $listingSortings;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
