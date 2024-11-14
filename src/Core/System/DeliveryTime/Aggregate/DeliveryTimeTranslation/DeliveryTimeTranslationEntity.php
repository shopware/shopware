<?php declare(strict_types=1);

namespace Shopware\Core\System\DeliveryTime\Aggregate\DeliveryTimeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

#[Package('checkout')]
class DeliveryTimeTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    /**
     * @var DeliveryTimeEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $deliveryTime;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $deliveryTimeId;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    public function getDeliveryTime(): ?DeliveryTimeEntity
    {
        return $this->deliveryTime;
    }

    public function setDeliveryTime(DeliveryTimeEntity $deliveryTime): void
    {
        $this->deliveryTime = $deliveryTime;
    }

    public function getDeliveryTimeId(): string
    {
        return $this->deliveryTimeId;
    }

    public function setDeliveryTimeId(string $deliveryTimeId): void
    {
        $this->deliveryTimeId = $deliveryTimeId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
