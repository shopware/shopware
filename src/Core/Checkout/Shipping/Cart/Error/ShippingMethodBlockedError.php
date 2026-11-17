<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Cart\Error;

use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ShippingMethodBlockedError extends Error
{
    private const KEY = 'shipping-method-blocked';

    /**
     * @param list<string> $deliveryIdentifiers
     */
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'reason', newType: 'string')]
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'id', newType: 'string')]
    public function __construct(
        protected readonly string $name,
        protected readonly ?string $id = null,
        protected readonly ?string $reason = null,
        protected array $deliveryIdentifiers = [],
    ) {
        if ($id === null || $reason === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Passing null for $id or $reason is deprecated and will not be allowed in v6.8.0.0. Please provide valid string values for both parameters.'
            );
        }

        $this->message = \sprintf(
            'Shipping method %s not available. Reason: %s',
            $name,
            $reason ?? 'No reason provided.',
        );

        parent::__construct($this->message);
    }

    /**
     * Lists the identifiers of the deliveries, leaving out deliveries without an identifier and those with negative shipping costs, like shipping discounts
     *
     * @param iterable<Delivery> $deliveries
     */
    public static function forDeliveries(ShippingMethodEntity $shippingMethod, string $reason, iterable $deliveries): self
    {
        $deliveryIdentifiers = [];

        foreach ($deliveries as $delivery) {
            if ($delivery->getIdentifier() !== null && $delivery->getShippingCosts()->getTotalPrice() >= 0) {
                $deliveryIdentifiers[] = $delivery->getIdentifier();
            }
        }

        return new self(
            name: (string) $shippingMethod->getTranslation('name'),
            id: $shippingMethod->getId(),
            reason: $reason,
            deliveryIdentifiers: $deliveryIdentifiers,
        );
    }

    public function isPersistent(): bool
    {
        return false;
    }

    public function getParameters(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'reason' => $this->reason,
            'deliveryIdentifiers' => $this->deliveryIdentifiers,
        ];
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    public function getShippingMethodId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * @return list<string>
     */
    public function getDeliveryIdentifiers(): array
    {
        return $this->deliveryIdentifiers;
    }

    /**
     * @internal
     */
    public function addDeliveryIdentifiersOf(self $error): void
    {
        $this->deliveryIdentifiers = array_values(array_unique([...$error->getDeliveryIdentifiers(), ...$this->deliveryIdentifiers]));
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function getId(): string
    {
        if (Feature::isActive('v6.8.0.0')) {
            \assert($this->id !== null);

            return \sprintf('%s-%s', self::KEY, $this->id);
        }

        return \sprintf('%s-%s', self::KEY, $this->name);
    }

    public function getLevel(): int
    {
        return self::LEVEL_WARNING;
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }
}
