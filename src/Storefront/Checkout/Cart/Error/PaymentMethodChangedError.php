<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PaymentMethodChangedError extends Error
{
    private const KEY = 'payment-method-changed';

    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'reason', newType: 'string')]
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'newPaymentMethodId', newType: 'string')]
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'oldPaymentMethodId', newType: 'string')]
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
            \assert($this->oldPaymentMethodId !== null && $this->newPaymentMethodId !== null);

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

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    public function getOldPaymentMethodId(): ?string
    {
        return $this->oldPaymentMethodId;
    }

    public function getOldPaymentMethodName(): string
    {
        return $this->oldPaymentMethodName;
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    public function getNewPaymentMethodId(): ?string
    {
        return $this->newPaymentMethodId;
    }

    public function getNewPaymentMethodName(): string
    {
        return $this->newPaymentMethodName;
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    public function getReason(): ?string
    {
        return $this->reason;
    }
}
