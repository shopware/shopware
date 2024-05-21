<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class SerializedField extends Field implements StorageAware
{
    public function __construct(
        private readonly string $storageName,
        string $propertyName,
        private readonly string $serializer = JsonFieldSerializer::class
    ) {
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    protected function getSerializerClass(): string
    {
        return $this->serializer;
    }
}
