<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceDefinitionFieldSerializer;

class PriceDefinitionField extends JsonField
{
    public function __construct(string $storageName, string $propertyName)
    {
        parent::__construct($storageName, $propertyName);
    }

    protected function getSerializerClass(): string
    {
        return PriceDefinitionFieldSerializer::class;
    }
}
