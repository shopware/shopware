<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ShippingMethodChangedError extends Error
{
    private const KEY = 'shipping-method-changed';

    public function __construct(
        private readonly string $oldShippingMethodName,
        private readonly string $newShippingMethodName
    ) {
        $this->message = \sprintf(
            '%s shipping is not available for your current cart, the shipping was changed to %s',
            $oldShippingMethodName,
            $newShippingMethodName
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
            'newShippingMethodName' => $this->getNewShippingMethodName(),
            'oldShippingMethodName' => $this->getOldShippingMethodName(),
        ];
    }

    public function blockOrder(): bool
    {
        return false;
    }

    public function getId(): string
    {
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

    public function getOldShippingMethodName(): string
    {
        return $this->oldShippingMethodName;
    }

    public function getNewShippingMethodName(): string
    {
        return $this->newShippingMethodName;
    }
}
