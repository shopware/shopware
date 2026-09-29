<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\PriceModifier;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification\OrderPriceModificationDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * `target` is frozen at order placement so a future recalculation knows which base to recompute
 * against without re-reading live plugin config: OrderPriceModificationProcessor recomputes the
 * persisted row on every order recalculation as `percentage` of the targeted totals. `percentage`
 * is signed like the resulting amount: negative reduces, positive surcharges (e.g. -10.0 for 10% off).
 */
#[Package('checkout')]
final class PriceModifierPercentagePriceDefinition extends Struct implements PriceModifierPriceDefinitionInterface
{
    final public const TYPE = OrderPriceModificationDefinition::PRICE_DEFINITION_TYPE_PERCENTAGE;

    /**
     * @param array{lineItems: bool, shipping: bool} $target
     */
    public function __construct(
        protected float $percentage,
        protected array $target,
        protected ?TaxRuleCollection $taxRules = null,
        protected bool $taxExempt = false,
        protected mixed $filter = null,
    ) {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getPercentage(): float
    {
        return $this->percentage;
    }

    /**
     * @return array{lineItems: bool, shipping: bool}
     */
    public function getTarget(): array
    {
        return $this->target;
    }

    public function getTaxRules(): ?TaxRuleCollection
    {
        return $this->taxRules;
    }

    public function isTaxExempt(): bool
    {
        return $this->taxExempt;
    }

    public function getFilter(): mixed
    {
        return $this->filter;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['type'] = $this->getType();

        return $data;
    }

    public function getApiAlias(): string
    {
        return 'cart_price_modifier_percentage_price_definition';
    }
}
