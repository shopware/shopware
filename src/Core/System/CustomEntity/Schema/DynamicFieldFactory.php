<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Schema;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension as DalExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Field\AssociationField;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal
 *
 * @phpstan-import-type CustomEntityField from CustomEntitySchemaUpdater
 */
#[Package('core')]
class DynamicFieldFactory
{
    /**
     * @param list<CustomEntityField> $fields
     */
    public static function create(ContainerInterface $container, string $entityName, array $fields): FieldCollection
    {
        $translated = [];

        $collection = new FieldCollection();

        foreach ($fields as $field) {
            $translatable = $field['translatable'] ?? false;
            if ($translatable) {
                $translated[] = $field;

                continue;
            }

            self::defineField($field, $collection, $entityName, $container);
        }

        if (empty($translated)) {
            return $collection;
        }

        $translations = new TranslationsAssociationField($entityName . '_translation', $entityName . '_id', 'translations', 'id');
        $collection->add($translations);

        foreach ($translated as &$field) {
            $required = $field['required'] ?? false;
            $apiAware = $field['storeApiAware'] ?? false;

            $property = self::kebabCaseToCamelCase($field['name']);
            unset($field['translatable']);

            $translatedField = new TranslatedField($property);
            if ($required) {
                $translations->addFlags(new Required());
            }
            if ($apiAware) {
                $translations->addFlags(new ApiAware());
                $translatedField->addFlags(new ApiAware());
            }
            $collection->add($translatedField);
        }

        unset($field);

        $registry = $container->get(DefinitionInstanceRegistry::class);
        if (!$registry instanceof DefinitionInstanceRegistry) {
            throw new ServiceNotFoundException(DefinitionInstanceRegistry::class);
        }

        $translation = DynamicTranslationEntityDefinition::create($entityName, $translated, $container);
        $container->set($translation->getEntityName(), $translation);
        $container->set($translation->getEntityName() . '.repository', self::createRepository($container, $translation));

        $registry->register($translation, $translation->getEntityName());

        return $collection;
    }

    private static function kebabCaseToCamelCase(string $string): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->denormalize(str_replace('-', '_', $string));
    }

    private static function createRepository(ContainerInterface $container, EntityDefinition $definition): EntityRepository
    {
        return new EntityRepository(
            $definition,
            $container->get(EntityReaderInterface::class),
            $container->get(VersionManager::class),
            $container->get(EntitySearcherInterface::class),
            $container->get(EntityAggregatorInterface::class),
            $container->get('event_dispatcher'),
            $container->get(EntityLoadedEventFactory::class)
        );
    }

    /**
     * @param CustomEntityField $field
     */
    private static function defineField(array $field, FieldCollection $collection, string $entityName, ContainerInterface $container): void
    {
        $registry = $container->get(DefinitionInstanceRegistry::class);
        if (!$registry instanceof DefinitionInstanceRegistry) {
            throw new ServiceNotFoundException(DefinitionInstanceRegistry::class);
        }

        $name = $field['name'];
        $required = ($field['required'] ?? false) ? new Required() : null;
        $inherited = $field['inherited'] ?? false;
        $apiAware = ($field['storeApiAware'] ?? false) ? new ApiAware() : null;

        $flags = \array_filter([$required, $apiAware]);

        $property = self::kebabCaseToCamelCase($name);

        switch ($field['type']) {
            case 'int':
                $collection->add(
                    (new IntField($name, $property))
                        ->addFlags(...$flags)
                );

                break;
            case 'bool':
                $collection->add(
                    (new BoolField($name, $property))
                        ->addFlags(...$flags)
                );

                break;
            case 'float':
                $collection->add(
                    (new FloatField($name, $property))
                        ->addFlags(...$flags)
                );

                break;
            case 'email':
                $collection->add(
                    (new EmailField($name, $property))
                        ->addFlags(...$flags)
                );

                break;
            case 'text':
                $instance = (new LongTextField($name, $property))
                    ->addFlags(...$flags);

                if ($field['allowHtml'] ?? false) {
                    $instance->addFlags(new AllowHtml(true));
                }

                $collection->add($instance);

                break;
            case 'price':
                $collection->add(
                    (new PriceField($name, $property))
                        ->addFlags(...$flags)
                );

                break;
            case 'date':
                $collection->add(
                    (new DateTimeField($name, $property))
                        ->addFlags(...$flags)
                );

                break;

            case 'json':
                $collection->add(
                    (new JsonField($name, $property))
                        ->addFlags(...$flags)
                );

                break;
            case 'many-to-many':

                // get reference entity definition to create bi-directionally associations
                $reference = $registry->getByEntityName($field['reference']);

                // build mapping name:   'custom_entity_blog_products'  => use field name instead of reference entity name to allow multiple references to same entity
                $mappingName = implode('_', [$entityName, $field['name']]);

                // create many-to-many association field for custom entity definition
                $association = new ManyToManyAssociationField($property, $field['reference'], $mappingName, $entityName . '_id', $field['reference'] . '_id', 'id', 'id');

                // mapping table records can always be deleted
                $association->addFlags(new CascadeDelete());

                // field is maybe flag to be store-api aware
                self::addFlag($association, $apiAware);

                // check product inheritance and add ReverseInherited(reverse-property-name)
                if ($reference->isInheritanceAware() && $inherited) {
                    $association->addFlags(new ReverseInherited(self::kebabCaseToCamelCase($mappingName)));
                }

                // association for custom entity definition: done
                $collection->add($association);

                // define mapping entity definition, fields are defined inside the definition class
                $definition = DynamicMappingEntityDefinition::create($entityName, $field['reference'], $mappingName);

                // register definition in container and definition registry
                $container->set($definition->getEntityName(), $definition);
                $container->set($definition->getEntityName() . '.repository', self::createRepository($container, $definition));
                $registry->register($definition, $definition->getEntityName());

                // define reverse side
                $property = self::kebabCaseToCamelCase($definition->getEntityName());

                // reverse property schema: #table#_#column# -  custom_entity_blog_products
                $association = new ManyToManyAssociationField($property, $entityName, $definition->getEntityName(), $field['reference'] . '_id', $entityName . '_id');
                $association->addFlags(new CascadeDelete());

                // if reference is not a custom entity definition, we need to add the dal extension flag to get the hydrated objects as `entity.extensions` value
                self::addFlag($association, self::getExtension($reference));

                // check for product inheritance use case
                if ($reference->isInheritanceAware() && $inherited) {
                    $association->addFlags(new Inherited());
                }

                $association->compile($registry);
                $reference->getFields()->addField($association);

                break;

            case 'many-to-one':
                // get reference entity definition to create bi-directionally associations
                $reference = $registry->getByEntityName($field['reference']);

                // build reverse property name: #table# _ #field#:  custom_entity_blog_top_seller: customEntityBlogTopSeller
                $reverse = self::kebabCaseToCamelCase($entityName . '_' . $name);

                // build foreign key field for custom entity table: custom_entity_blog_top_seller_id
                $foreignKey = (new FkField(self::id($name), $property . 'Id', $field['reference'], 'id'))->addFlags(...$flags);
                $collection->add($foreignKey);

                // now build association field for custom entity definition
                $association = new ManyToOneAssociationField($property, self::id($name), $field['reference'], 'id', false);

                // add flag for store-api awareness
                self::addFlag($association, $apiAware);

                // check for product inheritance use case and define reverse inherited flag. Used when joining from custom entity table to product table
                if ($reference->isInheritanceAware() && $inherited) {
                    $association->addFlags(new ReverseInherited($reverse));
                }
                $collection->add($association);

                if ($reference->isVersionAware()) {
                    // if reference is version aware, we need a reference version field inside the custom entity definition
                    $collection->add((new ReferenceVersionField($reference->getEntityName(), $name . '_version_id'))->addFlags(new Required()));
                }

                // now define reverse association
                $association = new OneToManyAssociationField($reverse, $entityName, self::id($name), 'id');

                // in sql we define the on-delete flag on the foreign key, for the DAL we need the flag on the reverse side, so we can check which association are affected when deleting the record (e.g. product)
                $association->addFlags(self::getOnDeleteFlag($field));

                // if reference is not a custom entity definition, we need to add the dal extension flag to get the hydrated objects as `entity.extensions` value
                self::addFlag($association, self::getExtension($reference));

                // check for product inheritance use case
                if ($reference->isInheritanceAware() && $inherited) {
                    $association->addFlags(new Inherited(self::id($field['name'])));
                }

                $association->compile($registry);
                $reference->getFields()->add($association);

                break;
            case 'one-to-one':
                // get reference entity definition to create bi-directionally associations
                $reference = $registry->getByEntityName($field['reference']);

                // build reverse property name: #table# _ #field#:  custom_entity_blog_top_seller: customEntityBlogTopSeller
                $reverse = self::kebabCaseToCamelCase($entityName . '_' . $name);

                // build foreign key field for custom entity table: custom_entity_blog_top_seller_id
                $foreignKey = (new FkField(self::id($name), $property . 'Id', $field['reference'], 'id'))->addFlags(...$flags);
                $collection->add($foreignKey);

                // now build association field for custom entity definition
                $association = new OneToOneAssociationField($property, self::id($name), 'id', $field['reference'], false);

                // add flag for store-api awareness
                self::addFlag($association, $apiAware);

                // check for product inheritance use case and define reverse inherited flag. Used when joining from custom entity table to product table
                if ($reference->isInheritanceAware() && $inherited) {
                    $association->addFlags(new ReverseInherited($reverse));
                }

                $collection->add($association);

                if ($reference->isVersionAware()) {
                    // if reference is version aware, we need a reference version field inside the custom entity definition
                    $collection->add((new ReferenceVersionField($reference->getEntityName(), $name . '_version_id'))->addFlags(new Required()));
                }

                // now define reverse association
                $association = new OneToOneAssociationField($reverse, 'id', self::id($name), $entityName, false);

                // in sql we define the on-delete flag on the foreign key, for the DAL we need the flag on the reverse side, so we can check which association are affected when deleting the record (e.g. product)
                $association->addFlags(self::getOnDeleteFlag($field));

                // if reference is not a custom entity definition, we need to add the dal extension flag to get the hydrated objects as `entity.extensions` value
                self::addFlag($association, self::getExtension($reference));

                // check for product inheritance use case
                if ($reference->isInheritanceAware() && $inherited) {
                    $association->addFlags(new Inherited(self::id($field['name'])));
                }

                $association->compile($registry);
                $reference->getFields()->addField($association);

                break;
            case 'one-to-many':
                // get reference entity definition to create bi-directionally associations
                $reference = $registry->getByEntityName($field['reference']);

                // build reverse property name: #table# _ #field#:  custom_entity_blog_comments/customEntityBlogComments
                $reverse = $entityName . '_' . $name;

                // build association for custom entity table: customEntityComments
                $association = new OneToManyAssociationField($property, $field['reference'], self::id($reverse), 'id');

                // in sql we define the on-delete flag on the foreign key, for the DAL we need the flag on the reverse side, so we can check which association are affected when deleting the record (e.g. product)
                $association->addFlags(self::getOnDeleteFlag($field));

                // add flag for store-api awareness
                self::addFlag($association, $apiAware);

                // check for product inheritance use case and define reverse inherited flag. Used when joining from custom entity table to product table
                if ($reference->isInheritanceAware() && $inherited) {
                    $association->addFlags(new ReverseInherited(self::kebabCaseToCamelCase($reverse)));
                }
                $collection->add($association);

                // now define the reverse side, starting with the foreign key field: custom_entity_blog_comments_id
                $fk = new FkField(self::id($reverse), self::kebabCaseToCamelCase(self::id($reverse)), $entityName, 'id');

                // add flag for store-api awareness
                self::addFlag($fk, $apiAware);

                // if reference is not a custom entity definition, we need to add the dal extension flag to get the hydrated objects as `entity.extensions` value
                $extension = self::getExtension($reference);
                self::addFlag($fk, $extension);

                // add required flag, should be set to true for aggregated entities (blog 1:N comments)
                $required = ($field['reverseRequired'] ?? false) ? new ApiAware() : null;
                self::addFlag($fk, $required);

                // compile foreign key and add to reference field collection - only compiled fields can be added after the field collection built
                $fk->compile($registry);
                $reference->getFields()->add($fk);

                // now build reverse many-to-one association: custom_entity_blog_comments::custom_entity_blog_comments
                $association = new ManyToOneAssociationField(self::kebabCaseToCamelCase($reverse), self::id($reverse), $entityName, 'id', false);
                self::addFlag($association, $extension);

                // check for product inheritance use case
                if ($reference->isInheritanceAware() && $inherited) {
                    $association->addFlags(new Inherited(self::id($field['name'])));
                }

                $association->compile($registry);
                $reference->getFields()->add($association);

                break;
            default:
                $collection->add(
                    (new StringField($name, $property))
                        ->addFlags(...$flags)
                );

                break;
        }
    }

    private static function addFlag(Field $field, ?Flag $flag): void
    {
        if ($flag !== null) {
            $field->addFlags($flag);
        }
    }

    private static function id(string $name): string
    {
        return $name . '_id';
    }

    /**
     * @param CustomEntityField $field
     */
    private static function getOnDeleteFlag(array $field): Flag
    {
        return match ($field['onDelete']) {
            AssociationField::CASCADE => new CascadeDelete(),
            AssociationField::SET_NULL => new SetNullOnDelete(),
            AssociationField::RESTRICT => new RestrictDelete(),
            default => throw new \RuntimeException(\sprintf('onDelete property %s are not supported on field %s', $field['onDelete'], $field['name'])),
        };
    }

    private static function getExtension(EntityDefinition $reference): ?DalExtension
    {
        if (str_starts_with($reference->getEntityName(), 'custom_entity_') || str_starts_with($reference->getEntityName(), 'ce_')) {
            return null;
        }

        return new DalExtension();
    }
}
