<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

class ConfigJsonField extends JsonField
{
    public const STORAGE_KEY = '_value';

    public function __construct(string $storageName, string $propertyName, array $propertyMapping = [])
    {
        $wrappedPropertyMapping = [
            new JsonField(self::STORAGE_KEY, self::STORAGE_KEY, $propertyMapping),
        ];
        parent::__construct($storageName, $propertyName, $wrappedPropertyMapping);
    }
}
