<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Struct\OrderStateTranslationSearchResult;
use Shopware\Framework\Event\NestedEvent;

class OrderStateTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_state_translation.search.result.loaded';

    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Struct\OrderStateTranslationSearchResult
     */
    protected $result;

    public function __construct(OrderStateTranslationSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
