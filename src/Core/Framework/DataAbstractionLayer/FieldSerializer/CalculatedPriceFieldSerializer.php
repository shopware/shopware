<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class CalculatedPriceFieldSerializer extends JsonFieldSerializer
{
    public function getFieldClass(): string
    {
        return CalculatedPriceField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        $value = json_decode(json_encode($data->getValue(), JSON_PRESERVE_ZERO_FRACTION), true);

        unset($value['extensions']);

        $data = new KeyValuePair($data->getKey(), $value, $data->isRaw());

        yield from parent::encode($field, $existence, $data, $parameters);
    }

    public function decode(Field $field, $value)
    {
        if ($value === null) {
            return null;
        }
        $value = parent::decode($field, $value);

        $taxRules = array_map(
            function (array $tax) {
                return new TaxRule(
                    (float) $tax['taxRate'],
                    (float) $tax['percentage']
                );
            },
            $value['taxRules']
        );

        $calculatedTaxes = array_map(
            function (array $tax) {
                return new CalculatedTax(
                    (float) $tax['tax'],
                    (float) $tax['taxRate'],
                    (float) $tax['price']
                );
            },
            $value['calculatedTaxes']
        );

        return new CalculatedPrice(
            (float) $value['unitPrice'],
            (float) $value['totalPrice'],
            new CalculatedTaxCollection($calculatedTaxes),
            new TaxRuleCollection($taxRules),
            (int) $value['quantity']
        );
    }
}
