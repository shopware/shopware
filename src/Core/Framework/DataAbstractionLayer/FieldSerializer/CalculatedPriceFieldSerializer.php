<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Pricing\PriceStruct;

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
        $value = $data->getValue();

        unset($value['extensions']);

        $data = new KeyValuePair($data->getKey(), $value, $data->isRaw());

        yield from parent::encode($field, $existence, $data, $parameters);
    }

    public function decode(Field $field, $value)
    {
        if ($value === null) {
            return null;
        }

        $value = json_decode((string) $value, true);

        return new PriceStruct($value['net'], $value['gross'], (bool) $value['linked']);
    }
}
