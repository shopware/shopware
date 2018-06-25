<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderState;

use Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\OrderStateTranslationCollection;
use Shopware\Core\Framework\ORM\Entity;

class OrderStateStruct extends Entity
{
    /**
     * @var string
     */
    protected $description;

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
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var OrderStateTranslationCollection|null
     */
    protected $translations;

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

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

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getTranslations(): ?OrderStateTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(OrderStateTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
