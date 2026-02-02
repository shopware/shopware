<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\AllowEmptyString as AllowEmptyStringAttr;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\AllowHtml as AllowHtmlAttr;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\AutoIncrement;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\CustomFields as CustomFieldsAttr;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Inherited as InheritedAttr;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey as PrimaryKeyAttr;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Protection;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ReferenceVersion;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Required as RequiredAttr;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ReverseInherited as ReverseInheritedAttr;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Serialized;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\State;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Version;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AsArray;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Uses FieldMetadata/FlagMetadata for type safety; converted to Symfony Definition
 * objects via toDefinition() for container compilation.
 *
 * @internal
 *
 * @final
 *
 * @phpstan-type AssociationAttribute OneToMany|ManyToMany|ManyToOne|OneToOne
 */
#[Package('framework')]
class AttributeEntityCompiler
{
    /**
     * @var list<class-string<Field>>
     */
    private const FIELD_ATTRIBUTES = [
        Translations::class,
        AutoIncrement::class,
        Serialized::class,
        ForeignKey::class,
        Version::class,
        Field::class,
        OneToMany::class,
        ManyToMany::class,
        ManyToOne::class,
        OneToOne::class,
        State::class,
        ReferenceVersion::class,
        CustomFieldsAttr::class,
    ];

    /**
     * @var list<class-string<AssociationAttribute>>
     *
     * @phpstan-var list<class-string<AssociationAttribute>>
     */
    private const ASSOCIATIONS = [
        OneToMany::class,
        ManyToMany::class,
        ManyToOne::class,
        OneToOne::class,
    ];

    private readonly CamelCaseToSnakeCaseNameConverter $converter;

    public function __construct()
    {
        $this->converter = new CamelCaseToSnakeCaseNameConverter();
    }

    /**
     * @param class-string<EntityStruct> $class
     */
    public function compile(string $class): CompiledDefinitions
    {
        $reflection = new \ReflectionClass($class);

        $collection = $reflection->getAttributes(Entity::class);

        if ($collection === []) {
            return new CompiledDefinitions(null);
        }

        $instance = $collection[0]->newInstance();

        $properties = $reflection->getProperties();

        $mappings = [];
        $fields = [];

        foreach ($properties as $property) {
            $field = $this->compileFieldFromProperty($property, $instance->name);

            if ($field === null) {
                continue;
            }

            $fields[] = $field;

            if ($field->attribute->type === ManyToMany::TYPE) {
                $mappings[] = $this->mapping($instance->name, $property);
            }
        }

        $entity = new EntityMetadata(
            $instance->name,
            $class,
            $instance->collectionClass,
            $instance->hydratorClass,
            $fields,
            $instance->since,
            $instance->parent,
        );

        return new CompiledDefinitions($entity, $mappings);
    }

    private function compileFieldFromProperty(\ReflectionProperty $property, string $entityName): ?FieldMetadata
    {
        $attribute = $this->getFieldAttribute($property);

        if ($attribute === null) {
            return null;
        }

        $attribute->nullable = $property->getType()?->allowsNull() ?? true;

        $type = $property->getType();
        $propertyType = $type instanceof \ReflectionNamedType ? $type->getName() : null;

        return new FieldMetadata(
            $attribute->getFieldClass(),
            $property->getName(),
            $attribute,
            $entityName,
            $this->compileFlags($attribute, $property),
            $propertyType,
        );
    }

    /**
     * @template TClassList of object
     *
     * @param class-string<TClassList> ...$list
     *
     * @return \ReflectionAttribute<TClassList>|null
     */
    private function getAttribute(\ReflectionProperty $property, string ...$list): ?\ReflectionAttribute
    {
        foreach ($list as $attribute) {
            $attribute = $property->getAttributes($attribute);
            if ($attribute !== []) {
                return $attribute[0];
            }
        }

        return null;
    }

    private function getFieldAttribute(\ReflectionProperty $property): ?Field
    {
        return $this->getAttribute($property, ...self::FIELD_ATTRIBUTES)?->newInstance();
    }

    /**
     * Duplicate flags (e.g., Required) may be added; Field::addFlags() deduplicates by class.
     *
     * @return list<FlagMetadata>
     */
    private function compileFlags(Field $field, \ReflectionProperty $property): array
    {
        $flags = [];

        if (!$field->nullable) {
            $flags[] = new FlagMetadata(Required::class);
        }

        if ($this->getAttribute($property, RequiredAttr::class)) {
            $flags[] = new FlagMetadata(Required::class);
        }

        // Translation association fields need to be marked as required,
        // because otherwise required fields in the association are not validated
        if ($field instanceof Translations) {
            $flags[] = new FlagMetadata(Required::class);
        }

        if ($this->getAttribute($property, PrimaryKeyAttr::class)) {
            $flags[] = new FlagMetadata(PrimaryKey::class);
            $flags[] = new FlagMetadata(Required::class);
        }

        if ($inherited = $this->getAttribute($property, InheritedAttr::class)) {
            $instance = $inherited->newInstance();
            $flags[] = new FlagMetadata(Inherited::class, [$instance->foreignKey]);
        }

        if ($reverseInherited = $this->getAttribute($property, ReverseInheritedAttr::class)) {
            $instance = $reverseInherited->newInstance();
            $flags[] = new FlagMetadata(ReverseInherited::class, [$instance->propertyName]);
        }

        if ($this->getAttribute($property, AllowEmptyStringAttr::class)) {
            $flags[] = new FlagMetadata(AllowEmptyString::class);
        }

        if ($attr = $this->getAttribute($property, AllowHtmlAttr::class)) {
            $instance = $attr->newInstance();
            $flags[] = new FlagMetadata(AllowHtml::class, [$instance->sanitized]);
        }

        if ($field->api !== false) {
            $aware = [];
            if (\is_array($field->api)) {
                if (isset($field->api['admin-api']) && $field->api['admin-api'] === true) {
                    $aware[] = AdminApiSource::class;
                }
                if (isset($field->api['store-api']) && $field->api['store-api'] === true) {
                    $aware[] = SalesChannelApiSource::class;
                }
            }
            $flags[] = new FlagMetadata(ApiAware::class, $aware);
        }

        if ($protection = $this->getAttribute($property, Protection::class)) {
            $protection = $protection->newInstance();
            $flags[] = new FlagMetadata(WriteProtected::class, array_values($protection->write));
        }

        if ($this->getAttribute($property, ManyToMany::class, OneToMany::class, Translations::class)) {
            $type = $property->getType();
            if ($type instanceof \ReflectionNamedType && $type->getName() === 'array') {
                $flags[] = new FlagMetadata(AsArray::class);
            }
        }

        if ($this->getAttribute($property, ReferenceVersion::class)) {
            $flags[] = new FlagMetadata(Required::class);
        }

        if ($association = $this->getAttribute($property, ...self::ASSOCIATIONS)) {
            $association = $association->newInstance();

            $onDeleteFlag = match ($association->onDelete) {
                OnDelete::CASCADE => new FlagMetadata(CascadeDelete::class),
                OnDelete::SET_NULL => new FlagMetadata(SetNullOnDelete::class),
                OnDelete::RESTRICT => new FlagMetadata(RestrictDelete::class),
                default => null,
            };

            if ($onDeleteFlag !== null) {
                $flags[] = $onDeleteFlag;
            }
        }

        // AutoIncrement and CustomFields should not be required
        if ($field->type === AutoIncrement::TYPE || $field->type === CustomFieldsAttr::TYPE) {
            $flags = array_values(array_filter(
                $flags,
                static fn (FlagMetadata $f) => $f->flagClass !== Required::class
            ));
        }

        return $flags;
    }

    private function mapping(string $entity, \ReflectionProperty $property): MappingMetadata
    {
        $attribute = $this->getAttribute($property, ManyToMany::class);

        if (!$attribute) {
            throw DataAbstractionLayerException::canNotFindAttribute(ManyToMany::class, $property->getName());
        }
        $field = $attribute->newInstance();

        $srcColumn = $entity . '_id';
        $refColumn = $field->entity . '_id';
        $srcProperty = $this->converter->denormalize($entity);
        $refProperty = $this->converter->denormalize($field->entity);

        $mappingName = $field->getMappingName($entity);

        $srcFk = new ForeignKey($entity, false, $srcColumn);
        $srcFk->nullable = false;
        $refFk = new ForeignKey($field->entity, false, $refColumn);
        $refFk->nullable = false;

        $srcAssoc = new ManyToOne($entity, OnDelete::NO_ACTION, 'id', false, $srcColumn);
        $srcAssoc->nullable = false;
        $refAssoc = new ManyToOne($field->entity, OnDelete::NO_ACTION, 'id', false, $refColumn);
        $refAssoc->nullable = false;

        $fields = [
            new FieldMetadata(
                FkField::class,
                $srcProperty . 'Id',
                $srcFk,
                $mappingName,
                [
                    new FlagMetadata(PrimaryKey::class),
                    new FlagMetadata(Required::class),
                ],
            ),
            new FieldMetadata(
                FkField::class,
                $refProperty . 'Id',
                $refFk,
                $mappingName,
                [
                    new FlagMetadata(PrimaryKey::class),
                    new FlagMetadata(Required::class),
                ],
            ),
            new FieldMetadata(
                ManyToOneAssociationField::class,
                $srcProperty,
                $srcAssoc,
                $mappingName,
            ),
            new FieldMetadata(
                ManyToOneAssociationField::class,
                $refProperty,
                $refAssoc,
                $mappingName,
            ),
        ];

        return new MappingMetadata(
            $mappingName,
            $fields,
            $entity,
            $field->entity,
        );
    }
}
