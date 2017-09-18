<?php declare(strict_types=1);

namespace Shopware\PriceGroupDiscount\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicCollection;

class PriceGroupDiscountBasicLoadedEvent extends NestedEvent
{
    const NAME = 'priceGroupDiscount.basic.loaded';

    /**
     * @var PriceGroupDiscountBasicCollection
     */
    protected $priceGroupDiscounts;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(PriceGroupDiscountBasicCollection $priceGroupDiscounts, TranslationContext $context)
    {
        $this->priceGroupDiscounts = $priceGroupDiscounts;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getPriceGroupDiscounts(): PriceGroupDiscountBasicCollection
    {
        return $this->priceGroupDiscounts;
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
