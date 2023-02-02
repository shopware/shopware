<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\CustomFieldsAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CustomFieldsSerializer;

class CustomFields extends JsonField
{
    public function __construct($storageName = 'custom_fields', $propertyName = 'customFields')
    {
        parent::__construct($storageName, $propertyName);
    }

    public function setPropertyMapping(array $propertyMapping): void
    {
        $this->propertyMapping = $propertyMapping;
    }

    protected function getSerializerClass(): string
    {
        return CustomFieldsSerializer::class;
    }

    protected function getAccessorBuilderClass(): ?string
    {
        return CustomFieldsAccessorBuilder::class;
    }
}
