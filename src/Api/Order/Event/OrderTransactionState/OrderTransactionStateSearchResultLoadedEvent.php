<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderTransactionState;

use Shopware\Api\Order\Struct\OrderTransactionStateSearchResult;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class OrderTransactionStateSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction_state.search.result.loaded';

    /**
     * @var OrderTransactionStateSearchResult
     */
    protected $result;

    public function __construct(OrderTransactionStateSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->result->getContext();
    }
}
