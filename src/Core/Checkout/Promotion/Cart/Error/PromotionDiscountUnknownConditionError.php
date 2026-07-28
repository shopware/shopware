<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\UnknownConditionRule;

/**
 * Warns that a promotion discount was dropped during (re)calculation because its price-definition
 * filter references a rule condition that is no longer registered, e.g. because the extension
 * providing it was uninstalled. Such conditions are substituted with a non-matching
 * {@see UnknownConditionRule} on decode, so the discount computes to zero and is removed -
 * this error makes that removal visible instead of silent.
 */
#[Package('checkout')]
class PromotionDiscountUnknownConditionError extends Error
{
    private const KEY = 'promotion-discount-unknown-condition';

    protected string $name;

    protected readonly string $discountLineItemId;

    public function __construct(
        LineItem $discountLineItem,
        protected readonly string $originalConditionName
    ) {
        $this->name = $discountLineItem->getLabel() ?? $discountLineItem->getId();
        $this->discountLineItemId = $discountLineItem->getId();
        $this->message = \sprintf(
            'Discount "%s" was removed: its condition "%s" is no longer available, e.g. because the extension providing it was uninstalled.',
            $this->name,
            $this->originalConditionName
        );

        parent::__construct($this->message);
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

    /**
     * The rule condition name that could not be resolved.
     */
    public function getOriginalConditionName(): string
    {
        return $this->originalConditionName;
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
        return [
            'name' => $this->name,
            'discountLineItemId' => $this->discountLineItemId,
            'originalConditionName' => $this->originalConditionName,
        ];
    }
}
