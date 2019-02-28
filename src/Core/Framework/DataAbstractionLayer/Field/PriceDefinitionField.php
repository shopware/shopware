<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;

class PriceDefinitionField extends JsonField
{
    public function __construct(string $storageName, string $propertyName)
    {
        // todo quantity and isCalculated should not be persisted
        $propertyMapping = [
            (new FloatField('price', 'price'))->setFlags(new Required()),
            (new JsonField('taxRules', 'taxRules'))->setFlags(new Required()),
            (new IntField('quantity', 'quantity'))->setFlags(new Required()),
            (new BoolField('isCalculated', 'isCalculated'))->setFlags(new Required()),
        ];

        parent::__construct($storageName, $propertyName, $propertyMapping);
    }
}
