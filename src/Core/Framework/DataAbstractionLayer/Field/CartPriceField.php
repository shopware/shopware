<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CartPriceFieldSerializer;

class CartPriceField extends JsonField
{
    public function __construct(string $storageName, string $propertyName)
    {
        $propertyMapping = [
            (new FloatField('netPrice', 'netPrice'))->setFlags(new Required()),
            (new FloatField('totalPrice', 'totalPrice'))->setFlags(new Required()),
            (new JsonField('calculatedTaxes', 'calculatedTaxes'))->setFlags(new Required()),
            (new JsonField('taxRules', 'taxRules'))->setFlags(new Required()),
            (new FloatField('positionPrice', 'positionPrice'))->setFlags(new Required()),
            (new StringField('taxStatus', 'taxStatus'))->setFlags(new Required()),
        ];

        parent::__construct($storageName, $propertyName, $propertyMapping);
    }

    protected function getSerializerClass(): string
    {
        return CartPriceFieldSerializer::class;
    }
}
