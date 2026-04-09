<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PaymentMethodBlockedError extends Error
{
    private const KEY = 'payment-method-blocked';

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - The order of parameters will be changed to: $id, $name, $reason
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $id will be of type string
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $reason will be of type string
     */
    public function __construct(
        protected readonly string $name,
        protected readonly ?string $reason = null,
        protected readonly ?string $id = null,
    ) {
        if ($id === null || $reason === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Passing null for $id or $reason is deprecated and will not be allowed in v6.8.0.0. Please provide valid string values for both parameters.'
            );
        }

        $this->message = \sprintf(
            'Payment method %s not available. Reason: %s',
            $name,
            $reason ?? 'No reason provided.',
        );

        parent::__construct($this->message);
    }

    public function getParameters(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'reason' => $this->reason,
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $id will be of type string
     */
    public function getPaymentMethodId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $reason will be of type string
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getId(): string
    {
        if (Feature::isActive('v6.8.0.0')) {
            \assert($this->id !== null);

            return \sprintf('%s-%s', self::KEY, $this->id);
        }

        return \sprintf('%s-%s', self::KEY, $this->name);
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getLevel(): int
    {
        return self::LEVEL_WARNING;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function isPersistent(): bool
    {
        return false;
    }
}
