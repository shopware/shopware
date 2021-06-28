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
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allows to hydrate database values into struct objects.
 */
class EntityHydrator
{
    private static array $hydrated = [];

    private static array $instances = [];

    /**
     * @var string[]
     */
    private static array $manyToOne = [];

    private static array $translatedFields = [];

    private ContainerInterface $container;

    /**
     * @psalm-suppress ContainerDependency
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function hydrate(EntityCollection $collection, string $entityClass, EntityDefinition $definition, array $rows, string $root, Context $context): EntityCollection
    {
        self::$hydrated = [];

        foreach ($rows as $row) {
            $collection->add($this->hydrateEntity($definition, $entityClass, $row, $root, $context));
        }

        return $collection;
    }

    final public static function createClass(string $class)
    {
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new $class();
        }

        // cloning instances is much faster than creating the class
        return clone self::$instances[$class];
    }

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
     */
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        $entity = $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getFields());

        return $entity;
    }

    protected function hydrateFields(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context, iterable $fields): Entity
    {
        /** @var ArrayStruct $foreignKeys */
        $foreignKeys = $entity->getExtension(EntityReader::FOREIGN_KEYS);

        foreach ($fields as $field) {
            $property = $field->getPropertyName();

            $key = $root . '.' . $property;

            if ($field instanceof ParentAssociationField) {
                continue;
            }

            if ($field instanceof ManyToManyAssociationField) {
                $this->manyToMany($row, $root, $entity, $field);

                continue;
            }

            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                $association = $this->manyToOne($row, $root, $field, $context);

                if ($field->is(Extension::class)) {
                    $entity->addExtension($property, $association);
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

        /** @var ArrayStruct $mapping */
        $mapping = $entity->getExtension(EntityReader::INTERNAL_MAPPING_STORAGE);

        $mapping->set($field->getPropertyName(), $ids);
    }

    protected function translate(EntityDefinition $definition, Entity $entity, array $row, string $root, Context $context, array $fields): void
    {
        $inherited = $definition->isInheritanceAware() && $context->considerInheritance();

        $chain = EntityDefinitionQueryHelper::buildTranslationChain($root, $context, $inherited);

        $translatedFields = $this->getTranslatedFields($definition, $fields);

        foreach ($translatedFields as $field => $typed) {
            $entity->addTranslated($field, $typed->getSerializer()->decode($typed, self::value($row, $root, $field)));

            $entity->$field = $typed->getSerializer()->decode($typed, self::value($row, $chain[0], $field));
        }
    }

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

        return $this->hydrateEntity($field->getReferenceDefinition(), $field->getReferenceDefinition()->getEntityClass(), $row, $association, $context);
    }

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
                $key = $accessor . '.' . $propertyName;
                $values[] = $row[$key] ?? null;
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

            $decoded = json_decode($string, true);

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

        return json_encode($merged, \JSON_PRESERVE_ZERO_FRACTION);
    }

    private function hydrateEntity(EntityDefinition $definition, string $entityClass, array $row, string $root, Context $context): Entity
    {
        $hydrator = $this->container->get($definition->getHydratorClass());

        if (!$hydrator instanceof self) {
            throw new \RuntimeException(sprintf('Hydrator for entity %s not registered', $definition->getEntityName()));
        }

        $identifier = implode('-', self::buildUniqueIdentifier($definition, $row, $root));

        $cacheKey = $root . $definition->getEntityName() . '::' . $identifier;

        if (isset(self::$hydrated[$cacheKey])) {
            return self::$hydrated[$cacheKey];
        }

        $entity = self::createClass($entityClass);

        $entity->addExtension(EntityReader::FOREIGN_KEYS, self::createClass(ArrayStruct::class));
        $entity->addExtension(EntityReader::INTERNAL_MAPPING_STORAGE, self::createClass(ArrayStruct::class));

        $entity->setUniqueIdentifier($identifier);
        $entity->internalSetEntityName($definition->getEntityName());

        $entity = $hydrator->assign($definition, $entity, $root, $row, $context);

        return self::$hydrated[$cacheKey] = $entity;
    }
}
