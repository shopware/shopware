<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateIntervalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Intentionally not final for BC as its extended by other field attributes (ForeignKey, ManyToOne, etc.).
 *
 * @phpstan-type FieldData array{type: string, translated: bool, api: bool|array{admin-api: bool, store-api: bool}, column: string|null, nullable: bool}
 *
 * @phpstan-ignore shopware.attributeNotFinal
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Field extends AbstractField
{
    /**
     * @var array<string, class-string<DalField>>
     */
    private const SCALAR_FIELD_MAP = [
        FieldType::INT => IntField::class,
        FieldType::FLOAT => FloatField::class,
        FieldType::BOOL => BoolField::class,
        FieldType::STRING => StringField::class,
        FieldType::TEXT => LongTextField::class,
        FieldType::DATETIME => DateTimeField::class,
        FieldType::DATE => DateField::class,
        FieldType::UUID => IdField::class,
        FieldType::JSON => JsonField::class,
        FieldType::DATE_INTERVAL => DateIntervalField::class,
        FieldType::TIME_ZONE => TimeZoneField::class,
        FieldType::ENUM => EnumField::class,
    ];

    public static function fromArray(array $data): Field
    {
        $instance = new Field(
            (string) $data['type'],
            (bool) $data['translated'],
            $data['api'],
            $data['column']
        );
        $instance->nullable = (bool) $data['nullable'];

        return $instance;
    }

    public function toDefinition(): Definition
    {
        $definition = new Definition(self::class);
        $definition->setFactory([self::class, 'fromArray']);
        $definition->setArguments([
            [
                'type' => $this->type,
                'translated' => $this->translated,
                'api' => $this->api,
                'column' => $this->column,
                'nullable' => $this->nullable,
            ],
        ]);

        return $definition;
    }

    public function createField(string $propertyName, string $column, string $entityName, ?string $propertyType = null): DalField
    {
        if ($this->type === FieldType::ENUM) {
            if ($propertyType === null || !is_a($propertyType, \BackedEnum::class, true)) {
                throw DataAbstractionLayerException::invalidEnumField($propertyName, $propertyType ?? 'null');
            }

            return new EnumField($this->column ?? $column, $propertyName, $propertyType::cases()[0]);
        }

        return parent::createField($propertyName, $column, $entityName, $propertyType);
    }

    /**
     * Resolution: Direct Field class (type: StringField::class) takes priority over SCALAR_FIELD_MAP lookup.
     *
     * @return class-string<DalField>
     */
    public function getFieldClass(): string
    {
        if (is_a($this->type, DalField::class, true)) {
            return $this->type;
        }

        return self::SCALAR_FIELD_MAP[$this->type]
            ?? throw DataAbstractionLayerException::unknownFieldAttributeType($this->type);
    }
}
