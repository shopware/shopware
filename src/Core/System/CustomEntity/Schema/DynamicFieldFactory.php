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
                $collection->add(
                    (new LongTextField($name, $property))
                        ->addFlags(...$flags)
                        ->addFlags(new AllowHtml(true))
                );

                break;
            case 'json':
                $collection->add(
                    (new JsonField($name, $property))
                        ->addFlags(...$flags)
                );

                break;
            case 'many-to-many':
                $mapping = [$entityName, $field['reference']];
                sort($mapping);

                $association = new ManyToManyAssociationField($property, DynamicEntityDefinition::class, DynamicMappingEntityDefinition::class, $entityName . '_id', $field['reference'] . '_id', 'id', 'id', implode('_', $mapping), $field['reference']);
                if ($apiAware) {
                    $association->addFlags($apiAware);
                }

                $collection->add($association);

                $mapping = DynamicMappingEntityDefinition::create($entityName, $field['reference']);
                $container->set($mapping->getEntityName(), $mapping);
                $registry->register($mapping, $mapping->getEntityName());

                break;

            case 'many-to-one':
                $collection->add(
                    (new FkField($name . '_id', $property . 'Id', DynamicEntityDefinition::class, 'id', $field['reference']))
                        ->addFlags(...$flags)
                );

                $association = new ManyToOneAssociationField($property, $name . '_id', DynamicEntityDefinition::class, 'id', false, $field['reference']);
                if ($apiAware) {
                    $association->addFlags($apiAware);
                }

                $collection->add($association);

                break;
            case 'one-to-one':
                $collection->add(
                    (new FkField($name . '_id', $property, DynamicEntityDefinition::class, 'id', $field['reference']))
                        ->addFlags(...$flags)
                );

                $association = new OneToOneAssociationField($property, $name . '_id', 'id', DynamicEntityDefinition::class, true, $field['reference']);
                if ($apiAware) {
                    $association->addFlags($apiAware);
                }

                $collection->add($association);

                break;
            case 'one-to-many':
                $association = new OneToManyAssociationField($property, DynamicEntityDefinition::class, $entityName . '_id', 'id', $field['reference']);
                if ($apiAware) {
                    $association->addFlags($apiAware);
                }

                $collection->add($association);

                self::addReverseForeignKey($registry, $field['reference'], $entityName, $apiAware);

                break;
            default:
                $collection->add(
                    (new StringField($name, $property))
                        ->addFlags(...$flags)
                );

                break;
        }
    }

    private static function addReverseForeignKey(DefinitionInstanceRegistry $registry, string $referenceEntity, string $entityName, ?ApiAware $apiAware): void
    {
        $reference = $registry->getByEntityName($referenceEntity);

        $fk = new FkField($entityName . '_id', self::kebabCaseToCamelCase($entityName) . 'Id', DynamicEntityDefinition::class, 'id', $entityName);
        if ($apiAware) {
            $fk->addFlags($apiAware);
        }

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
