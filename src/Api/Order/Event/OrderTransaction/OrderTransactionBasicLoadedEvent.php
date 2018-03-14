<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderTransaction;

use Shopware\Api\Order\Collection\OrderTransactionBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class OrderTransactionBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var OrderTransactionBasicCollection
     */
    protected $orderTransactions;

    public function __construct(OrderTransactionBasicCollection $orderTransactions, ShopContext $context)
    {
        $this->context = $context;
        $this->orderTransactions = $orderTransactions;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getOrderTransactions(): OrderTransactionBasicCollection
    {
        return $this->orderTransactions;
    }
}
