<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

class ShippingMethodBlockedError extends Error
{
    private const KEY = 'shipping-method-blocked';

    /**
     * @var string
     */
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->message = sprintf(
            'Shipping method %s not available',
            $name
        );

        parent::__construct($this->message);
    }

    public function isPersistent(): bool
    {
        return false;
    }

    public function getParameters(): array
    {
        return ['name' => $this->name];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function getId(): string
    {
        return sprintf('%s-%s', self::KEY, $this->name);
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
