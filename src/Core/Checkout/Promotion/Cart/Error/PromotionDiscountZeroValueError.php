<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;

/**
 * Informs that a redeemed promotion code was accepted and all of its conditions are met, but the
 * discount it grants for the current cart is 0,00, so no discount line item is added. This happens
 * for a discount that is configured with a value of zero, or for a fixed price discount that
 * already matches the price of the items it applies to.
 */
#[Package('checkout')]
class PromotionDiscountZeroValueError extends Error
{
    private const KEY = 'promotion-discount-zero-value';

    protected string $name;

    protected readonly string $discountLineItemId;

    public function __construct(LineItem $discountLineItem)
    {
        $this->name = $discountLineItem->getLabel() ?? $discountLineItem->getId();
        $this->discountLineItemId = $discountLineItem->getId();
        $this->message = \sprintf('Discount "%s" does not reduce the price of this cart.', $this->name);

        parent::__construct($this->message);
    }

    /**
     * The discount is recalculated on every cart calculation, so the notice is raised again as
     * long as the redeemed code stays without effect, and disappears once it grants a discount.
     */
    public function isPersistent(): bool
    {
        return false;
    }

    public function getId(): string
    {
        return \sprintf('%s-%s', self::KEY, $this->discountLineItemId);
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLevel(): int
    {
        return self::LEVEL_NOTICE;
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
        return [
            'name' => $this->name,
            'discountLineItemId' => $this->discountLineItemId,
        ];
    }
}
