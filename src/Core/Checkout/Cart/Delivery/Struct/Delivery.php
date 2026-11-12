<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class Delivery extends Struct
{
    protected ?int $position = null;

    /**
     * @var list<string>|null
     */
    private ?array $ruleIds = null;

    public function __construct(
        protected DeliveryPositionCollection $positions,
        protected DeliveryDate $deliveryDate,
        protected ShippingMethodEntity $shippingMethod,
        protected ShippingLocation $location,
        protected CalculatedPrice $shippingCosts
    ) {
    }

    public function getPositions(): DeliveryPositionCollection
    {
        return $this->positions;
    }

    public function getLocation(): ShippingLocation
    {
        return $this->location;
    }

    public function getDeliveryDate(): DeliveryDate
    {
        return $this->deliveryDate;
    }

    public function getShippingMethod(): ShippingMethodEntity
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodEntity $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }

    public function getShippingCosts(): CalculatedPrice
    {
        return $this->shippingCosts;
    }

    public function setShippingCosts(CalculatedPrice $shippingCosts): void
    {
        $this->shippingCosts = $shippingCosts;
    }

    /**
     * Position of the delivery in the cart, starting at 1, null until the delivery processor numbered it
     */
    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    /**
     * Ids of the rules that match this delivery, null while the delivery has not been matched
     *
     * @return list<string>|null
     */
    public function getRuleIds(): ?array
    {
        return $this->ruleIds;
    }

    /**
     * @param list<string>|null $ruleIds
     */
    public function setRuleIds(?array $ruleIds): void
    {
        $this->ruleIds = $ruleIds;
    }

    public function getApiAlias(): string
    {
        return 'cart_delivery';
    }
}
