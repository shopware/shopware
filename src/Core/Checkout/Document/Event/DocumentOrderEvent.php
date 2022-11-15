<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

abstract class DocumentOrderEvent extends Event
{
    private OrderCollection $orders;

    /**
     * @var DocumentGenerateOperation[]
     */
    private array $operations;

    private Context $context;

    /**
     * @param DocumentGenerateOperation[] $operations
     */
    public function __construct(OrderCollection $orders, array $operations, Context $context)
    {
        $this->orders = $orders;
        $this->operations = $operations;
        $this->context = $context;
    }

    /**
     * @return DocumentGenerateOperation[]
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrders(): OrderCollection
    {
        return $this->orders;
    }
}
