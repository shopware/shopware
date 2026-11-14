<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class Delivery extends Struct
{
    final public const DEFAULT_IDENTIFIER = 'default';

    protected ?string $identifier = null;

    /**
     * @var list<string>|null
     */
    private ?array $ruleIds = null;

    /**
     * @param string|null $identifier names the delivery within the cart: unique in the cart and the same for the same delivery in the next calculation
     */
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'identifier', newType: 'string', description: 'The parameter loses its null default and becomes required.')]
    public function __construct(
        protected DeliveryPositionCollection $positions,
        protected DeliveryDate $deliveryDate,
        protected ShippingMethodEntity $shippingMethod,
        protected ShippingLocation $location,
        protected CalculatedPrice $shippingCosts,
        ?string $identifier = null,
    ) {
        if ($identifier === null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Not passing $identifier to ' . static::class . ' is deprecated, it will be required in v6.8.0.0.');
        }

        $this->identifier = $identifier;
    }

    /**
     * Null for deliveries built without an identifier, which can not be addressed
     */
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    public function getIdentifier(): ?string
    {
        return $this->identifier;
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
