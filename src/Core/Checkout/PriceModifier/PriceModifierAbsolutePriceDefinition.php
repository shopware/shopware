<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\PriceModifier;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification\OrderPriceModificationDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
final class PriceModifierAbsolutePriceDefinition extends Struct implements PriceModifierPriceDefinitionInterface
{
    final public const TYPE = OrderPriceModificationDefinition::PRICE_DEFINITION_TYPE_ABSOLUTE;

    public function __construct(
        protected float $price,
        protected ?TaxRuleCollection $taxRules = null,
        protected bool $taxExempt = false,
        protected mixed $filter = null,
    ) {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getPrice(): float
    {
        return $this->price;
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
        return 'cart_price_modifier_absolute_price_definition';
    }
}
