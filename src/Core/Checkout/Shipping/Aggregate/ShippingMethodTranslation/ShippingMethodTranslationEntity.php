<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation;

use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ShippingMethodTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $shippingMethodId;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $description;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $trackingUrl;

    /**
     * @var ShippingMethodEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $shippingMethod;

    public function getShippingMethodId(): string
    {
        return $this->shippingMethodId;
    }

    public function setShippingMethodId(string $shippingMethodId): void
    {
        $this->shippingMethodId = $shippingMethodId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getTrackingUrl(): ?string
    {
        return $this->trackingUrl;
    }

    public function setTrackingUrl(?string $trackingUrl): void
    {
        $this->trackingUrl = $trackingUrl;
    }

    public function getShippingMethod(): ?ShippingMethodEntity
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodEntity $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }
}
