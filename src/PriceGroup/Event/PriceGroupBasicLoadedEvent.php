<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\PriceGroup\Struct\PriceGroupBasicCollection;

class PriceGroupBasicLoadedEvent extends NestedEvent
{
    const NAME = 'priceGroup.basic.loaded';

    /**
     * @var PriceGroupBasicCollection
     */
    protected $priceGroups;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(PriceGroupBasicCollection $priceGroups, TranslationContext $context)
    {
        $this->priceGroups = $priceGroups;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getPriceGroups(): PriceGroupBasicCollection
    {
        return $this->priceGroups;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];

        return new NestedEventCollection($events);
    }
}
