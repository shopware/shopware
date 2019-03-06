<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Pricing\Price;

class PriceFieldSerializer extends JsonFieldSerializer
{
    public function getFieldClass(): string
    {
        return PriceField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        $value = $data->getValue();

        unset($value['extensions']);
        if (!empty($value) && !isset($value['linked']) && !$existence->exists()) {
            $value['linked'] = false; // set default
        }

        $data = new KeyValuePair($data->getKey(), $value, $data->isRaw());

        yield from parent::encode($field, $existence, $data, $parameters);
    }

    public function decode(Field $field, $value)
    {
        if ($value === null) {
            return null;
        }
        $value = parent::decode($field, $value);

        return new Price($value['net'], $value['gross'], (bool) $value['linked']);
    }
}
