<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IntFieldSerializer;

class IntField extends Field implements StorageAware
{
    /**
     * @var string
     */
    private $storageName;

    /**
     * @var int|null
     */
    private $minValue;

    /**
     * @var int|null
     */
    private $maxValue;

    public function __construct(string $storageName, string $propertyName, ?int $minValue = null, ?int $maxValue = null)
    {
        $this->storageName = $storageName;
        $this->minValue = $minValue;
        $this->maxValue = $maxValue;
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getMinValue(): ?int
    {
        return $this->minValue;
    }

    public function getMaxValue(): ?int
    {
        return $this->maxValue;
    }

    protected function getSerializerClass(): string
    {
        return IntFieldSerializer::class;
    }
}
