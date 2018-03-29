<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderTransaction;

use Shopware\Api\Order\Collection\OrderTransactionBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class OrderTransactionBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var OrderTransactionBasicCollection
     */
    protected $orderTransactions;

    public function __construct(OrderTransactionBasicCollection $orderTransactions, ApplicationContext $context)
    {
        $this->context = $context;
        $this->orderTransactions = $orderTransactions;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getOrderTransactions(): OrderTransactionBasicCollection
    {
        return $this->orderTransactions;
    }
}
