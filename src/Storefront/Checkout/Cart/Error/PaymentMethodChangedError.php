<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PaymentMethodChangedError extends Error
{
    private const KEY = 'payment-method-changed';

    public function __construct(
        private readonly string $oldPaymentMethodName,
        private readonly string $newPaymentMethodName
    ) {
        $this->message = \sprintf(
            '%s payment is not available for your current cart, the payment was changed to %s',
            $oldPaymentMethodName,
            $newPaymentMethodName
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
            'newPaymentMethodName' => $this->getNewPaymentMethodName(),
            'oldPaymentMethodName' => $this->getOldPaymentMethodName(),
        ];
    }

    public function blockOrder(): bool
    {
        return false;
    }

    public function getId(): string
    {
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

    public function getOldPaymentMethodName(): string
    {
        return $this->oldPaymentMethodName;
    }

    public function getNewPaymentMethodName(): string
    {
        return $this->newPaymentMethodName;
    }
}
