<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\LongTextFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class LongTextField extends Field implements StorageAware
{
    public function __construct(
        private readonly string $storageName,
        string $propertyName
    ) {
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    protected function getSerializerClass(): string
    {
        return LongTextFieldSerializer::class;
    }
}
