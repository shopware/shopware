<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IdFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class IdField extends Field implements StorageAware
{
    public function __construct(
        protected string $storageName,
        string $propertyName
    ) {
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getExtractPriority(): int
    {
        return 75;
    }

    protected function getSerializerClass(): string
    {
        return IdFieldSerializer::class;
    }
}
