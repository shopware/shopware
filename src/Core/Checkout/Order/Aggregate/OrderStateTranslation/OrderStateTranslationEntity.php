<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation;

use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class OrderStateTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $orderStateId;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var OrderStateEntity|null
     */
    protected $orderState;

    public function getOrderStateId(): string
    {
        return $this->orderStateId;
    }

    public function setOrderStateId(string $orderStateId): void
    {
        $this->orderStateId = $orderStateId;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getOrderState(): ?OrderStateEntity
    {
        return $this->orderState;
    }

    public function setOrderState(OrderStateEntity $orderState): void
    {
        $this->orderState = $orderState;
    }
}
