<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\Write\Flag\Required;

class PriceField extends JsonArrayField
{
    public function __construct(string $storageName, string $propertyName)
    {
        $propertyMapping = [
            (new FloatField('gross', 'gross'))->setFlags(new Required()),
            (new FloatField('net', 'net'))->setFlags(new Required()),
        ];

        parent::__construct($storageName, $propertyName, $propertyMapping);
    }
}
