<?php declare(strict_types=1);

namespace Shopware\System\Listing\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Listing\Collection\ListingSortingBasicCollection;

class ListingSortingBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_sorting.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ListingSortingBasicCollection
     */
    protected $listingSortings;

    public function __construct(ListingSortingBasicCollection $listingSortings, Context $context)
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

    public function getListingSortings(): ListingSortingBasicCollection
    {
        return $this->listingSortings;
    }
}
