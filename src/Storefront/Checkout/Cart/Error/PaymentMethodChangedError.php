<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PaymentMethodChangedError extends Error
{
    private const KEY = 'payment-method-changed';

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - The order of parameters will be changed to: $oldPaymentMethodId, $oldPaymentMethodName, $newPaymentMethodId, $newPaymentMethodName
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $oldPaymentMethodId will be of type string
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $newPaymentMethodId will be of type string
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $reason will be of type string
     */
    public function __construct(
        protected readonly string $oldPaymentMethodName,
        protected readonly string $newPaymentMethodName,
        protected readonly ?string $oldPaymentMethodId = null,
        protected readonly ?string $newPaymentMethodId = null,
        protected readonly ?string $reason = null,
    ) {
        if ($oldPaymentMethodId === null || $newPaymentMethodId === null || $reason === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Passing null for $oldPaymentMethodId, $newPaymentMethodId, or $reason is deprecated and will not be allowed in v6.8.0.0. Please provide valid string values for both parameters.'
            );
        }

        $this->message = \sprintf(
            '%s payment is not available for your current cart, the payment was changed to %s. Reason: %s',
            $oldPaymentMethodName,
            $newPaymentMethodName,
            $reason,
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
            'oldPaymentMethodId' => $this->oldPaymentMethodId,
            'oldPaymentMethodName' => $this->oldPaymentMethodName,
            'newPaymentMethodId' => $this->newPaymentMethodId,
            'newPaymentMethodName' => $this->newPaymentMethodName,
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
            return \sprintf('%s-%s-%s', self::KEY, $this->oldPaymentMethodId, $this->newPaymentMethodId);
        }

        return \sprintf('%s-%s-%s', self::KEY, $this->oldPaymentMethodName, $this->newPaymentMethodName);
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
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $oldPaymentMethodId will be of type string
     */
    public function getOldPaymentMethodId(): ?string
    {
        return $this->oldPaymentMethodId;
    }

    public function getOldPaymentMethodName(): string
    {
        return $this->oldPaymentMethodName;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $newPaymentMethodId will be of type string
     */
    public function getNewPaymentMethodId(): ?string
    {
        return $this->newPaymentMethodId;
    }

    public function getNewPaymentMethodName(): string
    {
        return $this->newPaymentMethodName;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $reason will be of type string
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }
}
