<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\WasModifiedByUserFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class WasModifiedByUserField extends BoolField
{
    public function __construct(string $storageName = 'was_modified_by_user', string $propertyName = 'wasModifiedByUser')
    {
        parent::__construct($storageName, $propertyName);
    }

    protected function getSerializerClass(): string
    {
        return WasModifiedByUserFieldSerializer::class;
    }
}
