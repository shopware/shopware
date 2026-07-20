<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ShippingMethodChangedError extends Error
{
    private const KEY = 'shipping-method-changed';

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - The order of parameters will be changed to: $oldShippingMethodId, $oldShippingMethodName, $newShippingMethodId, $newShippingMethodName
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $oldShippingMethodId will be of type string
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $newShippingMethodId will be of type string
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $reason will be of type string
     */
    public function __construct(
        protected readonly string $oldShippingMethodName,
        protected readonly string $newShippingMethodName,
        protected readonly ?string $oldShippingMethodId = null,
        protected readonly ?string $newShippingMethodId = null,
        protected readonly ?string $reason = null,
    ) {
        if ($oldShippingMethodId === null || $newShippingMethodId === null || $reason === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Passing null for $oldShippingMethodId, $newShippingMethodId, or $reason is deprecated and will not be allowed in v6.8.0.0. Please provide valid string values for both parameters.'
            );
        }

        $this->message = \sprintf(
            '%s shipping is not available for your current cart, the shipping was changed to %s. Reason: %s',
            $oldShippingMethodName,
            $newShippingMethodName,
            $reason ?? 'No reason provided.',
        );

        parent::__construct($this->message);
    }

    public function isPersistent(): bool
    {
        return true;
    }

    public function getParameters(): array
    {
        return [
            'oldShippingMethodId' => $this->oldShippingMethodId,
            'oldShippingMethodName' => $this->oldShippingMethodName,
            'newShippingMethodId' => $this->newShippingMethodId,
            'newShippingMethodName' => $this->newShippingMethodName,
            'reason' => $this->reason,
        ];
    }

    public function blockOrder(): bool
    {
        return false;
    }

    public function getId(): string
    {
        if (Feature::isActive('v6.8.0.0')) {
            \assert($this->oldShippingMethodId !== null && $this->newShippingMethodId !== null);

            return \sprintf('%s-%s-%s', self::KEY, $this->oldShippingMethodId, $this->newShippingMethodId);
        }

        return \sprintf('%s-%s-%s', self::KEY, $this->oldShippingMethodName, $this->newShippingMethodName);
    }

    public function getLevel(): int
    {
        return self::LEVEL_NOTICE;
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $oldShippingMethodId will be of type string
     */
    public function getOldShippingMethodId(): ?string
    {
        return $this->oldShippingMethodId;
    }

    public function getOldShippingMethodName(): string
    {
        return $this->oldShippingMethodName;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $newShippingMethodId will be of type string
     */
    public function getNewShippingMethodId(): ?string
    {
        return $this->newShippingMethodId;
    }

    public function getNewShippingMethodName(): string
    {
        return $this->newShippingMethodName;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $reason will be of type string
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }
}
