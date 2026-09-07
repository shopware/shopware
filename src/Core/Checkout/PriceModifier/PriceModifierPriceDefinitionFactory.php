<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\PriceModifier;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification\OrderPriceModificationDefinition;
use Shopware\Core\Framework\Log\Package;

/**
 * The inverse of PriceModifierPriceDefinitionInterface's own jsonSerialize() -- hydrates the
 * persisted, plain-JSON price_definition column back into a real object for PriceModifier.
 */
#[Package('checkout')]
final class PriceModifierPriceDefinitionFactory
{
    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): ?PriceModifierPriceDefinitionInterface
    {
        if ($data === null) {
            return null;
        }

        $taxRules = self::taxRulesFromArray($data['taxRules'] ?? null);
        $taxExempt = (bool) ($data['taxExempt'] ?? false);
        $filter = $data['filter'] ?? null;

        return match ($data['type'] ?? null) {
            OrderPriceModificationDefinition::PRICE_DEFINITION_TYPE_ABSOLUTE => new PriceModifierAbsolutePriceDefinition(
                price: (float) ($data['price'] ?? 0.0),
                taxRules: $taxRules,
                taxExempt: $taxExempt,
                filter: $filter,
            ),
            OrderPriceModificationDefinition::PRICE_DEFINITION_TYPE_PERCENTAGE => new PriceModifierPercentagePriceDefinition(
                percentage: (float) ($data['percentage'] ?? 0.0),
                target: $data['target'] ?? ['lineItems' => true, 'shipping' => true],
                taxRules: $taxRules,
                taxExempt: $taxExempt,
                filter: $filter,
            ),
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>>|null $data
     */
    private static function taxRulesFromArray(?array $data): ?TaxRuleCollection
    {
        if ($data === null || $data === []) {
            return null;
        }

        return new TaxRuleCollection(array_map(
            static fn (array $rule): TaxRule => new TaxRule((float) $rule['taxRate'], (float) ($rule['percentage'] ?? 100.0)),
            $data
        ));
    }
}
