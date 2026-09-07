<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ProductPriceDefinitionRestoredError extends Error
{
    private const KEY = 'product-price-definition-restored';

    public function __construct(
        protected readonly string $lineItemId,
        protected readonly string $name,
    ) {
        $this->message = \sprintf(
            'The saved price definition for product "%s" was missing and has been restored from the order price. Review the price before saving the order.',
            $name,
        );

        parent::__construct($this->message);
    }

    public function getId(): string
    {
        return \sprintf('%s-%s', self::KEY, $this->lineItemId);
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
        return false;
    }

    /**
     * @return array<string, string>
     */
    public function getParameters(): array
    {
        return ['name' => $this->name];
    }

    public function getLineItemId(): string
    {
        return $this->lineItemId;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
