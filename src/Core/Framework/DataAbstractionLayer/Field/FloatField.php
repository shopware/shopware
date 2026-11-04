<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FloatFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class FloatField extends Field implements StorageAware
{
    public function __construct(
        private readonly string $storageName,
        string $propertyName,
        private readonly ?float $minValue = null,
        private readonly ?float $maxValue = null
    ) {
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getMinValue(): ?float
    {
        return $this->minValue;
    }

    public function getMaxValue(): ?float
    {
        return $this->maxValue;
    }

    protected function getSerializerClass(): string
    {
        return FloatFieldSerializer::class;
    }
}
