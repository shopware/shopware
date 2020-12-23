<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class CalculatedPriceFieldSerializer extends JsonFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        $value = json_decode(json_encode($data->getValue(), \JSON_PRESERVE_ZERO_FRACTION), true);

        unset($value['extensions']);
        if (isset($value['listPrice'])) {
            unset($value['listPrice']['extensions']);
        }

        $data->setValue($value);

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

        $referencePriceDefinition = null;
        if (isset($value['referencePrice'])) {
            $refPrice = $value['referencePrice'];

            $referencePriceDefinition = new ReferencePrice(
                $refPrice['price'],
                $refPrice['purchaseUnit'],
                $refPrice['referenceUnit'],
                $refPrice['unitName']
            );
        }

        $listPrice = null;
        if (isset($value['listPrice'])) {
            $listPrice = ListPrice::createFromUnitPrice(
                (float) $value['unitPrice'],
                (float) $value['listPrice']['price']
            );
        }

        return new CalculatedPrice(
            (float) $value['unitPrice'],
            (float) $value['totalPrice'],
            new CalculatedTaxCollection($calculatedTaxes),
            new TaxRuleCollection($taxRules),
            (int) $value['quantity'],
            $referencePriceDefinition,
            $listPrice
        );
    }
}
