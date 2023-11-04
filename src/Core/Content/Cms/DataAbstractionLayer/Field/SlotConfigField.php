<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataAbstractionLayer\Field;

use Shopware\Core\Content\Cms\DataAbstractionLayer\FieldSerializer\SlotConfigFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;

#[Package('content')]
class SlotConfigField extends JsonField
{
    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        $this->storageName = $storageName;
        parent::__construct($storageName, $propertyName);
    }

    protected function getSerializerClass(): string
    {
        return SlotConfigFieldSerializer::class;
    }
}
