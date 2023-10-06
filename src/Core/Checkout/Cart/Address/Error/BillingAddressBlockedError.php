<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class BillingAddressBlockedError extends Error
{
    private const KEY = 'billing-address-blocked';

    public function __construct(private readonly string $name)
    {
        $this->message = sprintf(
            'Billings to billing address %s are not possible.',
            $name
        );

        parent::__construct($this->message);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function getKey(): string
    {
        return sprintf('%s-%s', self::KEY, $this->name);
    }

    public function getLevel(): int
    {
        return self::LEVEL_ERROR;
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getId(): string
    {
        return $this->getKey();
    }

    public function getParameters(): array
    {
        return ['name' => $this->name];
    }
}
