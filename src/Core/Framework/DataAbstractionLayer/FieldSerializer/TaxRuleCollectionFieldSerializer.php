<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class TaxRuleCollectionFieldSerializer extends JsonFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        $value = $data->getValue();

        if ($value !== null) {
            $elements = $value instanceof TaxRuleCollection ? $value->getElements() : $value;

            // `percentage` only matters for CalculatedPrice's proportional-tax-split usage; here each
            // entry is purely a rate-eligibility filter, so it's fixed rather than taken from input.
            // OrderPriceModificationTaxLockValidator::taxRulesAreSemanticEqual() compares two
            // tax_rules values by decoding both through this class rather than as raw strings --
            // deliberately, since a native MySQL JSON column reformats whatever gets stored (added
            // whitespace, no guaranteed key order), so two byte-different strings can still be the
            // same semantic value. Do not "optimize" that comparison back to a raw `!==`.
            $value = array_values(array_map(
                static fn (TaxRule|array $rule): array => [
                    'taxRate' => $rule instanceof TaxRule ? $rule->getTaxRate() : (float) $rule['taxRate'],
                    'percentage' => 100.0,
                ],
                $elements
            ));
        }

        $data->setValue($value);

        yield from parent::encode($field, $existence, $data, $parameters);
    }

    public function decode(Field $field, mixed $value): ?TaxRuleCollection
    {
        if ($value === null) {
            return null;
        }

        $decoded = parent::decode($field, $value);
        if (!\is_array($decoded)) {
            return null;
        }

        return new TaxRuleCollection(array_map(
            static fn (array $rule): TaxRule => new TaxRule((float) $rule['taxRate'], (float) ($rule['percentage'] ?? 100.0)),
            $decoded
        ));
    }
}
