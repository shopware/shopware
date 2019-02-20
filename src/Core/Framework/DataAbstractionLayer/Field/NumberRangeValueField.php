<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

class NumberRangeValueField extends StringField
{
    public function __construct(string $storageName, string $propertyName, int $maxLength = 64)
    {
        parent::__construct($storageName, $propertyName, $maxLength);
    }
}
