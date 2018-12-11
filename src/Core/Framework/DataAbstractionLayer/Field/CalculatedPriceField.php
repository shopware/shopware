<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class CalculatedPriceField extends JsonField
{
    public function __construct(string $storageName, string $propertyName)
    {
        $propertyMapping = [
            (new FloatField('unitPrice', 'unitPrice'))->setFlags(new Required()),
            (new FloatField('totalPrice', 'totalPrice'))->setFlags(new Required()),
            (new IntField('quantity', 'quantity'))->setFlags(new Required()),
            (new JsonField('calculatedTaxes', 'calculatedTaxes'))->setFlags(new Required()),
            (new JsonField('taxRules', 'taxRules'))->setFlags(new Required()),
        ];

        parent::__construct($storageName, $propertyName, $propertyMapping);
    }
}
