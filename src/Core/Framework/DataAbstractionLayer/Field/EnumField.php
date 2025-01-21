<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Doctrine\DBAL\Types\Types;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\EnumFieldSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * Stores a PHP Enum
 */
#[Package('framework')]
class EnumField extends Field implements StorageAware
{
    private string $type;

    /**
     * @param \BackedEnum $enum Any case from the used Enum may be passed.
     */
    public function __construct(
        private readonly string $storageName,
        string $propertyName,
        private \BackedEnum $enum
    ) {
        parent::__construct($propertyName);
        $backingType = (new \ReflectionEnum($enum::class))->getBackingType();
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
     * @return \BackedEnum Any case from the mapped Enum.
     */
    public function getEnum(): \BackedEnum
    {
        return $this->enum;
    }

    /**
     * @return string The DBAL {@see Types type} of the field. Supports {@see Types::STRING} when
     *                {@see self::$enum} is {@see \StringBackedEnum} and {@see Types::INTEGER} for
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
