<?php declare(strict_types=1);

namespace Shopware\Api\Entity;

use Shopware\Api\Entity\Field\AssociationInterface;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\WrittenEvent;
use Shopware\Context\Struct\TranslationContext;

abstract class EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function addExtension(EntityExtensionInterface $extension): void
    {
        static::$extensions[get_class($extension)] = $extension;
        static::$fields = null;
    }

    abstract public static function getEntityName(): string;

    abstract public static function getFields(): FieldCollection;

    abstract public static function getRepositoryClass(): string;

    abstract public static function getBasicCollectionClass(): string;

    abstract public static function getBasicStructClass(): string;

    abstract public static function getWrittenEventClass(): string;

    abstract public static function getTranslationDefinitionClass(): ?string;

    public static function getWriteOrder(): array
    {
        return array_values(
            array_merge(
                static::filterAssociationReferences(ManyToOneAssociationField::class, static::getFields()),
                array_filter([static::class, static::getTranslationDefinitionClass()]),
                static::filterAssociationReferences(OneToManyAssociationField::class, static::getFields()),
                static::filterAssociationReferences(ManyToManyAssociationField::class, static::getFields())
            )
        );
    }

    public static function getPrimaryKeys(): FieldCollection
    {
        if (static::$primaryKeys) {
            return static::$primaryKeys;
        }

        return static::$primaryKeys = static::getFields()
            ->filter(function (Field $field) {
                return $field->is(PrimaryKey::class);
            });
    }

    public static function createWrittenEvent(array $identifiers, TranslationContext $context, array $errors): ?WrittenEvent
    {
        if (!array_key_exists(ProductDefinition::class, $identifiers)) {
            return null;
        }

        $uuids = $identifiers[ProductDefinition::class];
        $class = self::getWrittenEventClass();

        return new $class($uuids, $context, $errors);
    }

    public static function getDefaults(string $type): array
    {
        return [];
    }

    public static function getDetailStructClass(): string
    {
        return static::getBasicStructClass();
    }

    public static function getDetailCollectionClass(): string
    {
        return static::getBasicCollectionClass();
    }

    protected static function filterAssociationReferences(string $type, FieldCollection $fields)
    {
        $associations = $fields->filterInstance($type)->getElements();

        $associations = array_map(function (AssociationInterface $association) {
            if ($association->getReferenceClass() !== static::class) {
                return $association->getReferenceClass();
            }

            return null;
        }, $associations);

        return array_filter($associations);
    }
}
