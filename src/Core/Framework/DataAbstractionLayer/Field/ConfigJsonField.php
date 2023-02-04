<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\ConfigJsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ConfigJsonFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ConfigJsonField extends JsonField
{
    final public const STORAGE_KEY = '_value';

    public function __construct(
        string $storageName,
        string $propertyName,
        array $propertyMapping = []
    ) {
        $wrappedPropertyMapping = [
            new JsonField(self::STORAGE_KEY, self::STORAGE_KEY, $propertyMapping),
        ];
        parent::__construct($storageName, $propertyName, $wrappedPropertyMapping);
    }

    protected function getSerializerClass(): string
    {
        return ConfigJsonFieldSerializer::class;
    }

    protected function getAccessorBuilderClass(): ?string
    {
        return ConfigJsonFieldAccessorBuilder::class;
    }
}
