<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\DataAbstractionLayer\Field;

use Shopware\Core\Content\Flow\DataAbstractionLayer\FieldSerializer\FlowTemplateConfigFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;

class FlowTemplateConfigField extends JsonField
{
    public function __construct(string $storageName, string $propertyName)
    {
        $this->storageName = $storageName;
        parent::__construct($storageName, $propertyName);
    }

    protected function getSerializerClass(): string
    {
        return FlowTemplateConfigFieldSerializer::class;
    }
}
