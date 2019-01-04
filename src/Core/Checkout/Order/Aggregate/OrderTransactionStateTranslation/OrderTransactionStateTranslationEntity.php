<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\OrderTransactionStateEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class OrderTransactionStateTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $orderTransactionStateId;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var OrderTransactionStateEntity
     */
    protected $orderTransactionState;

    public function getOrderTransactionStateId(): string
    {
        return $this->orderTransactionStateId;
    }

    public function setOrderTransactionStateId(string $orderTransactionStateId): void
    {
        $this->orderTransactionStateId = $orderTransactionStateId;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getOrderTransactionState(): OrderTransactionStateEntity
    {
        return $this->orderTransactionState;
    }

    public function setOrderTransactionState(OrderTransactionStateEntity $orderTransactionState): void
    {
        $this->orderTransactionState = $orderTransactionState;
    }
}
