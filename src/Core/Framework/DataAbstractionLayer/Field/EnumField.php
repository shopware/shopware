<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Doctrine\DBAL\Types\Types;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\EnumFieldSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * Stores a PHP enumeration
 */
#[Package('core')]
class EnumField extends Field implements StorageAware, TypeVariant
{
    private string $type;

    /**
     * @param \BackedEnum $enumeration Any case from the used enumeration may be passed.
     */
    public function __construct(
        private readonly string $storageName,
        string $propertyName,
        private \BackedEnum $enumeration
    ) {
        parent::__construct($propertyName);
        $backingType = (new \ReflectionEnum($enumeration::class))->getBackingType();
        $this->type = match ($backingType?->getName()) {
            'int' => Types::INTEGER,
            'string' => Types::STRING,
            default => throw DataAbstractionLayerException::fieldHasNoType(static::class),
        };
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * @return \BackedEnum Any case from the mapped enumeration.
     */
    public function getEnumeration(): \BackedEnum
    {
        return $this->enumeration;
    }

    /**
     * @return string The DBAL {@see Types type} of the field. Supports {@see Types::STRING} when
     *                {@see self::$enumeration} is {@see \StringBackedEnum} and {@see Types::INTEGER} for
     *                {@see \IntBackedEnum}
     */
    public function getType(): string
    {
        return $this->type;
    }

    protected function getSerializerClass(): string
    {
        return EnumFieldSerializer::class;
    }
}
