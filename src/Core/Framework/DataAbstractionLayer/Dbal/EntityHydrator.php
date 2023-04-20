<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allows to hydrate database values into struct objects.
 *
 * @internal
 */
#[Package('core')]
class EntityHydrator
{
    /**
     * @var array<mixed>
     */
    protected static array $partial = [];

    /**
     * @var array<mixed>
     */
    private static array $hydrated = [];

    /**
     * @var array<string>
     */
    private static array $manyToOne = [];

    /**
     * @var array<string, array<string, Field>>
     */
    private static array $translatedFields = [];

    /**
     * @internal
     */
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * @param EntityCollection<Entity> $collection
     * @param array<mixed> $rows
     * @param array<string|array<string>> $partial
     *
     * @return EntityCollection<Entity>
     */
    public function hydrate(EntityCollection $collection, string $entityClass, EntityDefinition $definition, array $rows, string $root, Context $context, array $partial = []): EntityCollection
    {
        self::$hydrated = [];

        self::$partial = $partial;

        if (!empty(self::$partial)) {
            $collection = new EntityCollection();
        }

        foreach ($rows as $row) {
            $collection->add($this->hydrateEntity($definition, $entityClass, $row, $root, $context, $partial));
        }

        return $collection;
    }

    /**
     * @template EntityClass
     *
     * @param class-string<EntityClass> $class
     *
     * @return EntityClass
     */
    final public static function createClass(string $class)
    {
        return new $class();
    }

    /**
     * @param array<mixed> $row
     *
     * @return array<mixed>
     */
    final public static function buildUniqueIdentifier(EntityDefinition $definition, array $row, string $root): array
    {
        $primaryKeyFields = $definition->getPrimaryKeys();
        $primaryKey = [];

        foreach ($primaryKeyFields as $field) {
            if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                continue;
            }
            $accessor = $root . '.' . $field->getPropertyName();

            $primaryKey[$field->getPropertyName()] = $field->getSerializer()->decode($field, $row[$accessor]);
        }

        return $primaryKey;
    }

    /**
     * @param array<string> $primaryKey
     *
     * @return array<string>
     */
    final public static function encodePrimaryKey(EntityDefinition $definition, array $primaryKey, Context $context): array
    {
        $fields = $definition->getPrimaryKeys();

        $mapped = [];

        $existence = new EntityExistence($definition->getEntityName(), [], true, false, false, []);

        $params = new WriteParameterBag($definition, WriteContext::createFromContext($context), '', new WriteCommandQueue());

        foreach ($fields as $field) {
            if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                $value = $context->getVersionId();
            } else {
                $value = $primaryKey[$field->getPropertyName()];
            }

            $kvPair = new KeyValuePair($field->getPropertyName(), $value, true);

            $encoded = $field->getSerializer()->encode($field, $existence, $kvPair, $params);

            foreach ($encoded as $key => $value) {
                $mapped[$key] = $value;
            }
        }

        return $mapped;
    }

    /**
     * Allows simple overwrite for specialized entity hydrators
     *
     * @param array<mixed> $row
     */
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        $entity = $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getFields());

        return $entity;
    }

    /**
     * @param array<mixed> $row
     * @param iterable<Field> $fields
     */
    protected function hydrateFields(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context, iterable $fields): Entity
    {
        /** @var ArrayStruct<string, mixed> $foreignKeys */
        $foreignKeys = $entity->getExtension(EntityReader::FOREIGN_KEYS);
        $isPartial = self::$partial !== [];

        foreach ($fields as $field) {
            $property = $field->getPropertyName();

            if ($isPartial && !isset(self::$partial[$property])) {
                continue;
            }

            $key = $root . '.' . $property;

            // initialize not loaded associations with null
            if ($field instanceof AssociationField && $entity instanceof ArrayEntity) {
                $entity->set($property, null);
            }

            if ($field instanceof ParentAssociationField) {
                continue;
            }

            if ($field instanceof ManyToManyAssociationField) {
                $this->manyToMany($row, $root, $entity, $field);

                continue;
            }

            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                $association = $this->manyToOne($row, $root, $field, $context);

                if ($association === null && $entity instanceof PartialEntity) {
                    continue;
                }

                if ($field->is(Extension::class)) {
                    if ($association) {
                        $entity->addExtension($property, $association);
                    }
                } else {
                    $entity->assign([$property => $association]);
                }

                continue;
            }

            //other association fields are not handled in entity reader query
            if ($field instanceof AssociationField) {
                continue;
            }

            if (!\array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            $typed = $field;
            if ($field instanceof TranslatedField) {
                $typed = EntityDefinitionQueryHelper::getTranslatedField($definition, $field);
            }

            if ($typed instanceof CustomFields) {
                $this->customFields($definition, $row, $root, $entity, $field, $context);

                continue;
            }

            if ($field instanceof TranslatedField) {
                // contains the resolved translation chain value
                $decoded = $typed->getSerializer()->decode($typed, $value);
                $entity->addTranslated($property, $decoded);

                $inherited = $definition->isInheritanceAware() && $context->considerInheritance();
                $chain = EntityDefinitionQueryHelper::buildTranslationChain($root, $context, $inherited);

                // assign translated value of the first language
                $key = array_shift($chain) . '.' . $property;

                $decoded = $typed->getSerializer()->decode($typed, $row[$key]);
                $entity->assign([$property => $decoded]);

                continue;
            }

            $decoded = $definition->decode($property, $value);

            if ($field->is(Extension::class)) {
                $foreignKeys->set($property, $decoded);
            } else {
                $entity->assign([$property => $decoded]);
            }
        }

        return $entity;
    }

    /**
     * @param array<mixed> $row
     */
    protected function manyToMany(array $row, string $root, Entity $entity, ?Field $field): void
    {
        if ($field === null) {
            throw new \RuntimeException('No field provided');
        }

        $accessor = $root . '.' . $field->getPropertyName() . '.id_mapping';

        //many to many isn't loaded in case of limited association criterias
        if (!\array_key_exists($accessor, $row)) {
            return;
        }

        //explode hexed ids
        $ids = explode('||', (string) $row[$accessor]);

        $ids = array_map('strtolower', array_filter($ids));

        /** @var ArrayStruct<string, mixed> $mapping */
        $mapping = $entity->getExtension(EntityReader::INTERNAL_MAPPING_STORAGE);

        $mapping->set($field->getPropertyName(), $ids);
    }

    /**
     * @param array<mixed> $row
     * @param array<string, Field> $fields
     */
    protected function translate(EntityDefinition $definition, Entity $entity, array $row, string $root, Context $context, array $fields): void
    {
        $inherited = $definition->isInheritanceAware() && $context->considerInheritance();

        $chain = EntityDefinitionQueryHelper::buildTranslationChain($root, $context, $inherited);

        $translatedFields = $this->getTranslatedFields($definition, $fields);

        foreach ($translatedFields as $field => $typed) {
            $entity->addTranslated($field, $typed->getSerializer()->decode($typed, self::value($row, $root, $field)));

            $entity->$field = $typed->getSerializer()->decode($typed, self::value($row, $chain[0], $field)); /* @phpstan-ignore-line */
        }
    }

    /**
     * @param array<Field> $fields
     *
     * @return array<string, Field>
     */
    protected function getTranslatedFields(EntityDefinition $definition, array $fields): array
    {
        $key = $definition->getEntityName();
        if (isset(self::$translatedFields[$key])) {
            return self::$translatedFields[$key];
        }

        $translatedFields = [];
        /** @var TranslatedField $field */
        foreach ($fields as $field) {
            $translatedFields[$field->getPropertyName()] = EntityDefinitionQueryHelper::getTranslatedField($definition, $field);
        }

        return self::$translatedFields[$key] = $translatedFields;
    }

    /**
     * @param array<mixed> $row
     */
    protected function manyToOne(array $row, string $root, ?Field $field, Context $context): ?Entity
    {
        if ($field === null) {
            throw new \RuntimeException('No field provided');
        }

        if (!$field instanceof AssociationField) {
            throw new \RuntimeException(sprintf('Provided field %s is no association field', $field->getPropertyName()));
        }
        $pk = $this->getManyToOneProperty($field);

        $association = $root . '.' . $field->getPropertyName();

        $key = $association . '.' . $pk;

        if (!isset($row[$key])) {
            return null;
        }

        return $this->hydrateEntity($field->getReferenceDefinition(), $field->getReferenceDefinition()->getEntityClass(), $row, $association, $context, self::$partial[$field->getPropertyName()] ?? []);
    }

    /**
     * @param array<mixed> $row
     */
    protected function customFields(EntityDefinition $definition, array $row, string $root, Entity $entity, ?Field $field, Context $context): void
    {
        if ($field === null) {
            return;
        }

        $inherited = $field->is(Inherited::class) && $context->considerInheritance();

        $propertyName = $field->getPropertyName();

        $value = self::value($row, $root, $propertyName);

        if ($field instanceof TranslatedField) {
            $customField = EntityDefinitionQueryHelper::getTranslatedField($definition, $field);

            $chain = EntityDefinitionQueryHelper::buildTranslationChain($root, $context, $inherited);

            $decoded = $customField->getSerializer()->decode($customField, self::value($row, $chain[0], $propertyName));

            $entity->assign([$propertyName => $decoded]);

            $values = [];
            foreach ($chain as $accessor) {
                $values[] = self::value($row, $accessor, $propertyName);
            }

            if (empty($values)) {
                return;
            }

            /**
             * `array_merge`s ordering is reversed compared to the translations array.
             * In other terms: The first argument has the lowest 'priority', so we need to reverse the array
             */
            $merged = $this->mergeJson(array_reverse($values, false));
            $decoded = $customField->getSerializer()->decode($customField, $merged);
            $entity->addTranslated($propertyName, $decoded);

            if ($inherited) {
                /*
                 * The translations chains array has the structure: [
                 *      main language,
                 *      parent with main language,
                 *      fallback language,
                 *      parent with fallback language,
                 * ]
                 *
                 * We need to join the first two to get the inherited field value of the main translation
                 */
                $values = [
                    self::value($row, $chain[0], $propertyName),
                    self::value($row, $chain[1], $propertyName),
                ];

                $merged = $this->mergeJson(array_reverse($values, false));
                $decoded = $customField->getSerializer()->decode($customField, $merged);
                $entity->assign([$propertyName => $decoded]);
            }

            return;
        }

        // field is not inherited or request should work with raw data? decode child attributes and return
        if (!$inherited) {
            $value = $field->getSerializer()->decode($field, $value);
            $entity->assign([$propertyName => $value]);

            return;
        }

        $parentKey = $root . '.' . $propertyName . '.inherited';

        // parent has no attributes? decode only child attributes and return
        if (!isset($row[$parentKey])) {
            $value = $field->getSerializer()->decode($field, $value);

            $entity->assign([$propertyName => $value]);

            return;
        }

        // merge child attributes with parent attributes and assign
        $mergedJson = $this->mergeJson([$row[$parentKey], $value]);

        $merged = $field->getSerializer()->decode($field, $mergedJson);

        $entity->assign([$propertyName => $merged]);
    }

    /**
     * @param array<mixed> $row
     */
    protected static function value(array $row, string $root, string $property): ?string
    {
        $accessor = $root . '.' . $property;

        return $row[$accessor] ?? null;
    }

    protected function getManyToOneProperty(AssociationField $field): string
    {
        $key = $field->getReferenceDefinition()->getEntityName() . '.' . $field->getReferenceField();
        if (isset(self::$manyToOne[$key])) {
            return self::$manyToOne[$key];
        }

        $reference = $field->getReferenceDefinition()->getFields()->getByStorageName(
            $field->getReferenceField()
        );

        if ($reference === null) {
            throw new \RuntimeException(sprintf(
                'Can not find field by storage name %s in definition %s',
                $field->getReferenceField(),
                $field->getReferenceDefinition()->getEntityName()
            ));
        }

        return self::$manyToOne[$key] = $reference->getPropertyName();
    }

    /**
     * @param array<string|null> $jsonStrings
     */
    protected function mergeJson(array $jsonStrings): string
    {
        $merged = [];
        foreach ($jsonStrings as $string) {
            if ($string === null) {
                continue;
            }

            $decoded = json_decode($string, true, 512, \JSON_THROW_ON_ERROR);

            if (!$decoded) {
                continue;
            }

            foreach ($decoded as $key => $value) {
                if ($value === null) {
                    continue;
                }

                $merged[$key] = $value;
            }
        }

        return json_encode($merged, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<mixed> $row
     * @param array<string|array<string>> $partial
     */
    private function hydrateEntity(EntityDefinition $definition, string $entityClass, array $row, string $root, Context $context, array $partial = []): Entity
    {
        $isPartial = $partial !== [];
        $hydratorClass = $definition->getHydratorClass();
        $entityClass = $isPartial ? PartialEntity::class : $entityClass;

        if ($isPartial) {
            $hydratorClass = EntityHydrator::class;
        }

        $hydrator = $this->container->get($hydratorClass);

        if (!$hydrator instanceof self) {
            throw new \RuntimeException(sprintf('Hydrator for entity %s not registered', $definition->getEntityName()));
        }

        $identifier = implode('-', self::buildUniqueIdentifier($definition, $row, $root));

        $cacheKey = $root . '::' . $identifier;

        if (isset(self::$hydrated[$cacheKey])) {
            return self::$hydrated[$cacheKey];
        }

        $entity = new $entityClass();

        if (!$entity instanceof Entity) {
            throw new \RuntimeException(sprintf('Expected instance of Entity.php, got %s', $entity::class));
        }

        $entity->addExtension(EntityReader::FOREIGN_KEYS, new ArrayStruct([], $definition->getEntityName() . '_foreign_keys_extension'));
        $entity->addExtension(EntityReader::INTERNAL_MAPPING_STORAGE, new ArrayStruct());

        $entity->setUniqueIdentifier($identifier);
        $entity->internalSetEntityData($definition->getEntityName(), $definition->getFieldVisibility());

        $entity = $hydrator->assign($definition, $entity, $root, $row, $context);

        return self::$hydrated[$cacheKey] = $entity;
    }
}
