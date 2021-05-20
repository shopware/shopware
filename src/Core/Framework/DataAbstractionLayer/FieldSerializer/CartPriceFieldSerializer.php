<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class CartPriceFieldSerializer extends JsonFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        $value = json_decode(json_encode($data->getValue(), \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR), true);

        unset($value['extensions']);

        $data->setValue($value);

        yield from parent::encode($field, $existence, $data, $parameters);
    }

    /**
     * @return CartPrice|null
     *
     * @deprecated tag:v6.5.0 The parameter $value and return type will be native typed
     */
    public function decode(Field $field, /*?string */$value)/*: ?CartPrice*/
    {
        if ($value === null) {
            return null;
        }

        $decoded = parent::decode($field, $value);
        if ($decoded === null) {
            return null;
        }

        $taxRules = array_map(
            function (array $tax) {
                return new TaxRule(
                    (float) $tax['taxRate'],
                    (float) $tax['percentage']
                );
            },
            $decoded['taxRules']
        );

        $calculatedTaxes = array_map(
            function (array $tax) {
                return new CalculatedTax(
                    (float) $tax['tax'],
                    (float) $tax['taxRate'],
                    (float) $tax['price']
                );
            },
            $decoded['calculatedTaxes']
        );

        return new CartPrice(
            (float) $decoded['netPrice'],
            (float) $decoded['totalPrice'],
            (float) $decoded['positionPrice'],
            new CalculatedTaxCollection($calculatedTaxes),
            new TaxRuleCollection($taxRules),
            (string) $decoded['taxStatus'],
            isset($decoded['rawTotal']) ? (float) $decoded['rawTotal'] : (float) $decoded['totalPrice']
        );
    }
}
