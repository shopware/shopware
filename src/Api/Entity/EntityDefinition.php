<?php declare(strict_types=1);

namespace Shopware\Api\Entity;

use Shopware\Api\Entity\Field\AssociationInterface;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\WrittenEvent;
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

    abstract public static function getDeletedEventClass(): string;

    abstract public static function getTranslationDefinitionClass(): ?string;

    public static function getWriteOrder(): array
    {
        $manyToOne = static::filterAssociationReferences(ManyToOneAssociationField::class, static::getFields());

        $oneToMany = static::filterAssociationReferences(OneToManyAssociationField::class, static::getFields());

        $manyToMany = static::filterAssociationReferences(ManyToManyAssociationField::class, static::getFields());

        $self = array_filter([static::class, static::getTranslationDefinitionClass()]);

        /*
         * If a linked entity exists once as OneToMany but also as ManyToOne (bi-directional foreign keys),
         * it must be treated as OneToMany. In the MySQL database,
         * no foreign key may be created for the ManyToOne relation.
         *
         * Examples:
         *      a customer has 1:N addresses
         *      a customer has 1:1 default_shipping_address
         *      a customer has 1:1 default_billing_address
         */
        $c = array_intersect($manyToOne, $oneToMany);
        foreach ($c as $index => $value) {
            unset($manyToOne[$index]);
        }

        return array_unique(array_values(array_merge($manyToOne, $self, $oneToMany, $manyToMany)));
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
        if (!array_key_exists(static::class, $identifiers)) {
            return null;
        }

        $ids = $identifiers[static::class];
        $class = self::getWrittenEventClass();

        return new $class($ids, $context, $errors);
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
