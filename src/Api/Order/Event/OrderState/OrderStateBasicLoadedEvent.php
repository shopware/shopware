<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderState;

use Shopware\Api\Order\Collection\OrderStateBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class OrderStateBasicLoadedEvent extends NestedEvent
{
    const NAME = 'order_state.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var OrderStateBasicCollection
     */
    protected $orderStates;

    public function __construct(OrderStateBasicCollection $orderStates, TranslationContext $context)
    {
        $this->context = $context;
        $this->orderStates = $orderStates;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getOrderStates(): OrderStateBasicCollection
    {
        return $this->orderStates;
    }
}
