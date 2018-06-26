<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\OrderTransactionStateTranslationCollection;
use Shopware\Core\Framework\ORM\Entity;

class OrderTransactionStateStruct extends Entity
{
    /**
     * @var int
     */
    protected $position;

    /**
     * @var bool
     */
    protected $hasMail;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var OrderTransactionStateTranslationCollection|null
     */
    protected $translations;

    /**
     * @var OrderTransactionCollection|null
     */
    protected $orderTransactions;

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getHasMail(): bool
    {
        return $this->hasMail;
    }

    public function setHasMail(bool $hasMail): void
    {
        $this->hasMail = $hasMail;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getTranslations(): ?OrderTransactionStateTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(OrderTransactionStateTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getOrderTransactions(): ?OrderTransactionCollection
    {
        return $this->orderTransactions;
    }

    public function setOrderTransactions(?OrderTransactionCollection $orderTransactions): void
    {
        $this->orderTransactions = $orderTransactions;
    }
}
