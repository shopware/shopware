<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

class AttributesField extends JsonField
{
    public function __construct($storageName = 'attributes', $propertyName = 'attributes')
    {
        parent::__construct($storageName, $propertyName);
    }
}
