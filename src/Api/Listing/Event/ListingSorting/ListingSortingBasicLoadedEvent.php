<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Event\ListingSorting;

use Shopware\Api\Listing\Collection\ListingSortingBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class ListingSortingBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_sorting.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ListingSortingBasicCollection
     */
    protected $listingSortings;

    public function __construct(ListingSortingBasicCollection $listingSortings, ShopContext $context)
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

    public function getListingSortings(): ListingSortingBasicCollection
    {
        return $this->listingSortings;
    }
}
