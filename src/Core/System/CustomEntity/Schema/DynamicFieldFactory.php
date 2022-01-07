<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Schema;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension as DalExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal
 */
class DynamicFieldFactory
{
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

        $translations = new TranslationsAssociationField(DynamicTranslationEntityDefinition::class, $entityName . '_id', 'translations', 'id', $entityName . '_translation');
        $collection->add($translations);

        foreach ($translated as &$field) {
            $required = $field['required'] ?? false;
            $apiAware = $field['storeApiAware'] ? new ApiAware() : null;

            $property = self::kebabCaseToCamelCase($field['name']);
            unset($field['translatable']);

            if ($required) {
                $translations->addFlags(new Required());
            }
            $translations->addFlags($apiAware);

            $collection->add(
                (new TranslatedField($property))
                    ->addFlags($apiAware)
            );
        }

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

    private static function defineField(array $field, FieldCollection $collection, string $entityName, ContainerInterface $container): void
    {
        $registry = $container->get(DefinitionInstanceRegistry::class);
        if (!$registry instanceof DefinitionInstanceRegistry) {
            throw new ServiceNotFoundException(DefinitionInstanceRegistry::class);
        }

        $name = $field['name'];
        $required = $field['required'] ?? false;
        $required = $required ? new Required() : null;
        $apiAware = $field['storeApiAware'] ? new ApiAware() : null;

        $property = self::kebabCaseToCamelCase($name);

        switch ($field['type']) {
            case 'int':
                $collection->add(
                    (new IntField($name, $property))
                        ->addFlags($required)
                        ->addFlags($apiAware)
                );

                break;
            case 'bool':
                $collection->add(
                    (new BoolField($name, $property))
                        ->addFlags($required)
                        ->addFlags($apiAware)
                );

                break;
            case 'float':
                $collection->add(
                    (new FloatField($name, $property))
                        ->addFlags($required)
                        ->addFlags($apiAware)
                );

                break;
            case 'email':
                $collection->add(
                    (new EmailField($name, $property))
                        ->addFlags($required)
                        ->addFlags($apiAware)
                );

                break;
            case 'text':
                $collection->add(
                    (new LongTextField($name, $property))
                        ->addFlags($required)
                        ->addFlags($apiAware)
                        ->addFlags(new AllowHtml(true))
                );

                break;
            case 'json':
                $collection->add(
                    (new JsonField($name, $property))
                        ->addFlags($required)
                        ->addFlags($apiAware)
                );

                break;
            case 'many-to-many':
                $mapping = [$entityName, $field['reference']];
                sort($mapping);

                $collection->add(
                    (new ManyToManyAssociationField($property, DynamicEntityDefinition::class, DynamicMappingEntityDefinition::class, $entityName . '_id', $field['reference'] . '_id', 'id', 'id', implode('_', $mapping), $field['reference']))
                        ->addFlags($apiAware)
                );

                $mapping = DynamicMappingEntityDefinition::create($entityName, $field['reference']);
                $container->set($mapping->getEntityName(), $mapping);
                $registry->register($mapping, $mapping->getEntityName());

                break;

            case 'many-to-one':
                $collection->add(
                    (new FkField($name . '_id', $property . 'Id', DynamicEntityDefinition::class, 'id', $field['reference']))
                        ->addFlags($apiAware)
                        ->addFlags($required)
                );

                $collection->add(
                    (new ManyToOneAssociationField($property, $name . '_id', DynamicEntityDefinition::class, 'id', false, $field['reference']))
                        ->addFlags($apiAware)
                );

                break;
            case 'one-to-one':
                $collection->add(
                    (new FkField($name . '_id', $property, DynamicEntityDefinition::class, 'id', $field['reference']))
                        ->addFlags($apiAware)
                        ->addFlags($required)
                );

                $collection->add(
                    (new OneToOneAssociationField($property, $name . '_id', 'id', DynamicEntityDefinition::class, true, $field['reference']))
                        ->addFlags($apiAware)
                );

                break;
            case 'one-to-many':
                $collection->add(
                    (new OneToManyAssociationField($property, DynamicEntityDefinition::class, $entityName . '_id', 'id', $field['reference']))
                        ->addFlags($apiAware)
                );

                self::addReverseForeignKey($registry, $field['reference'], $entityName, $apiAware);

                break;
            default:
                $collection->add(
                    (new StringField($name, $property))
                        ->addFlags($apiAware)
                        ->addFlags($required)
                );

                break;
        }
    }

    private static function addReverseForeignKey(DefinitionInstanceRegistry $registry, string $referenceEntity, string $entityName, ?ApiAware $apiAware): void
    {
        $reference = $registry->getByEntityName($referenceEntity);

        $fk = new FkField($entityName . '_id', self::kebabCaseToCamelCase($entityName) . 'Id', DynamicEntityDefinition::class, 'id', $entityName);
        $fk->addFlags($apiAware);

        $isCustomEntity = strpos($reference->getEntityName(), 'custom_entity_') === 0;

        if ($isCustomEntity) {
            $fk->addFlags(new Required());
        } else {
            $fk->addFlags(new DalExtension());
        }

        $fk->compile($registry);
        $reference->getFields()->add($fk);

        $association = new ManyToOneAssociationField(self::kebabCaseToCamelCase($entityName), $entityName . '_id', DynamicEntityDefinition::class, 'id', false, $entityName);
        if (!$isCustomEntity) {
            $association->addFlags(new DalExtension());
        }

        $association->compile($registry);
        $reference->getFields()->add($association);
    }
}
