<?php declare(strict_types=1);

namespace Shopware\Core\Content\DeliveryTime\Aggregate\DeliveryTimeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class DeliveryTimeTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $deliveryTimeId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var \DateTimeInterface|null
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $updatedAt;

    /**
     * @var array|null
     */
    protected $customFields;

    public function getDeliveryTimeId(): string
    {
        return $this->deliveryTimeId;
    }

    public function setDeliveryTimeId(string $deliveryTimeId): void
    {
        $this->deliveryTimeId = $deliveryTimeId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }
}
