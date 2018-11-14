<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\Pricing\PriceStruct;

class PriceFieldSerializer extends JsonFieldSerializer
{
    public function getFieldClass(): string
    {
        return PriceField::class;
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
