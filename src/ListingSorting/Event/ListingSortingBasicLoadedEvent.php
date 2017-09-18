<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\ListingSorting\Struct\ListingSortingBasicCollection;

class ListingSortingBasicLoadedEvent extends NestedEvent
{
    const NAME = 'listingSorting.basic.loaded';

    /**
     * @var ListingSortingBasicCollection
     */
    protected $listingSortings;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ListingSortingBasicCollection $listingSortings, TranslationContext $context)
    {
        $this->listingSortings = $listingSortings;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getListingSortings(): ListingSortingBasicCollection
    {
        return $this->listingSortings;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
        ]);
    }
}
