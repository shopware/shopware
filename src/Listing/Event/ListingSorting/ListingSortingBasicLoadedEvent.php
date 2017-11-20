<?php declare(strict_types=1);

namespace Shopware\Listing\Event\ListingSorting;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Listing\Collection\ListingSortingBasicCollection;

class ListingSortingBasicLoadedEvent extends NestedEvent
{
    const NAME = 'listing_sorting.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ListingSortingBasicCollection
     */
    protected $listingSortings;

    public function __construct(ListingSortingBasicCollection $listingSortings, TranslationContext $context)
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

    public function getListingSortings(): ListingSortingBasicCollection
    {
        return $this->listingSortings;
    }
}
