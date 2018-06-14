<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Struct\OrderTransactionStateTranslationSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class OrderTransactionStateTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction_state_translation.search.result.loaded';

    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Struct\OrderTransactionStateTranslationSearchResult
     */
    protected $result;

    public function __construct(OrderTransactionStateTranslationSearchResult $result)
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
