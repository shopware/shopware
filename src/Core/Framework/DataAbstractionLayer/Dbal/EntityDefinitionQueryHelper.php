<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Inherited;
use Shopware\Core\Framework\Struct\Uuid;

/**
 * This class acts only as helper/common class for all dbal operations for entity definitions.
 * It knows how an association should be joined, how a parent-child inheritance should act, how translation chains work, ...
 */
class EntityDefinitionQueryHelper
{
    public const HAS_TO_MANY_JOIN = 'has_to_many_join';

    /**
     * @var FieldResolverRegistry
     */
    private $fieldResolverRegistry;

    /**
     * @var FieldAccessorBuilderRegistry
     */
    private $fieldAccessorBuilderRegistry;

    public function __construct(
        FieldResolverRegistry $fieldResolverRegistry,
        FieldAccessorBuilderRegistry $fieldAccessorBuilderRegistry
    ) {
        $this->fieldResolverRegistry = $fieldResolverRegistry;
        $this->fieldAccessorBuilderRegistry = $fieldAccessorBuilderRegistry;
    }

    public static function escape(string $string): string
    {
        return '`' . $string . '`';
    }

    /**
     * Returns the field instance of the provided fieldName.
     *
     * @example
     *
     * fieldName => 'product.name'
     * Returns the (new TranslatedField('name')) declaration
     *
     * Allows additionally nested referencing
     *
     * fieldName => 'category.products.name'
     * Returns as well the above field definition
     */
    public function getField(string $fieldName, string $definition, string $root): ?Field
    {
        $original = $fieldName;
        $prefix = $root . '.';

        if (strpos($fieldName, $prefix) === 0) {
            $fieldName = substr($fieldName, \strlen($prefix));
        }

        /** @var EntityDefinition $definition */
        $fields = $definition::getFields();

        $isAssociation = strpos($fieldName, '.') !== false;

        if (!$isAssociation && $fields->has($fieldName)) {
            return $fields->get($fieldName);
        }
        $associationKey = explode('.', $fieldName);
        $associationKey = array_shift($associationKey);

        $field = $fields->get($associationKey);

        if ($field instanceof TranslatedField) {
            return $this->getTranslatedField($definition, $field);
        }

        if (!$field instanceof AssociationInterface) {
            return $field;
        }

        /** @var AssociationInterface $field */
        $referenceClass = $field->getReferenceClass();
        if ($field instanceof ManyToManyAssociationField) {
            $referenceClass = $field->getReferenceDefinition();
        }

        return $this->getField(
            $original,
            $referenceClass,
            $root . '.' . $field->getPropertyName()
        );
    }

    /**
     * Builds the sql field accessor for the provided field.
     *
     * @example
     *
     * fieldName => product.taxId
     * root      => product
     * returns   => `product`.`tax_id`
     *
     * This function is also used for complex field accessors like JsonArray Field, JsonObject fields.
     * It considers the translation and parent-child inheritance.
     *
     * fieldName => product.name
     * root      => product
     * return    => COALESCE(`product.translation`.`name`,`product.parent.translation`.`name`)
     *
     * @throws UnmappedFieldException
     */
    public function getFieldAccessor(string $fieldName, string $definition, string $root, Context $context): string
    {
        $original = $fieldName;
        $prefix = $root . '.';

        if (strpos($fieldName, $prefix) === 0) {
            $fieldName = substr($fieldName, \strlen($prefix));
        }

        /** @var EntityDefinition $definition */
        $fields = $definition::getFields();
        if ($fields->has($fieldName)) {
            $field = $fields->get($fieldName);

            return $this->buildInheritedAccessor($field, $root, $definition, $context, $fieldName);
        }

        $associationKey = explode('.', $fieldName);
        $associationKey = array_shift($associationKey);

        if (!$fields->has($associationKey)) {
            throw new UnmappedFieldException($original, $definition);
        }

        /** @var AssociationInterface|Field $field */
        $field = $fields->get($associationKey);

        //case for json object fields, other fields has now same option to act with more point notations but hasn't to be an association field. E.g. price.gross
        if (!$field instanceof AssociationInterface && $field instanceof StorageAware) {
            return $this->buildInheritedAccessor($field, $root, $definition, $context, $fieldName);
        }

        $referenceClass = $field->getReferenceClass();
        if ($field instanceof ManyToManyAssociationField) {
            $referenceClass = $field->getReferenceDefinition();
        }

        return $this->getFieldAccessor(
            $original,
            $referenceClass,
            $root . '.' . $field->getPropertyName(),
            $context
        );
    }

    /**
     * Creates the basic root query for the provided entity definition and application context.
     * It considers the current context version and the catalog restrictions.
     */
    public function getBaseQuery(QueryBuilder $query, string $definition, Context $context): QueryBuilder
    {
        /** @var string|EntityDefinition $definition */
        $table = $definition::getEntityName();

        $query->from(self::escape($table), self::escape($table));

        if ($definition::isVersionAware() && $context->getVersionId() !== Defaults::LIVE_VERSION) {
            $this->joinVersion($query, $definition, $definition::getEntityName(), $context);
        } elseif ($definition::isVersionAware()) {
            $query->andWhere(self::escape($table) . '.`version_id` = :version');
            $query->setParameter('version', Uuid::fromStringToBytes($context->getVersionId()));
        }

        if ($definition::isCatalogAware() && $context->getCatalogIds() !== null) {
            $catalogIds = array_map(function (string $catalogId) {
                return Uuid::fromHexToBytes($catalogId);
            }, $context->getCatalogIds());

            $query->andWhere(self::escape($table) . '.`catalog_id` IN (:catalogIds)');
            $query->setParameter('catalogIds', $catalogIds, Connection::PARAM_STR_ARRAY);
        }

        if ($definition::isBlacklistAware() && $context->getRules()) {
            $condition = static::buildRuleFieldWhere($context, $table);

            foreach ($condition['parameters'] as $key => $value) {
                $query->setParameter($key, $value);
            }

            $query->andWhere(implode(' + ', $condition['wheres']) . ' = 0');
        }

        return $query;
    }

    public static function buildRuleFieldWhere(Context $context, string $root): array
    {
        $wheres = [];
        $parameters = [];

        foreach ($context->getRules() as $id) {
            $key = 'blacklist' . $id;

            $parameters[$key] = $id;
            $wheres[] = sprintf(
                'JSON_CONTAINS(IFNULL(%s.%s, JSON_ARRAY()), JSON_ARRAY(:%s))',
                self::escape($root),
                '`blacklisted_rule_ids`',
                $key
            );
        }

        return [
            'parameters' => $parameters,
            'wheres' => $wheres
        ];
    }

    /**
     * Used for dynamic sql joins. In case that the given fieldName is unknown or event nested with multiple association
     * roots, the function can resolve each association part of the field name, even if one part of the fieldName contains a translation or event inherited data field.
     */
    public function resolveAccessor(string $fieldName, string $definition, string $root, QueryBuilder $query, Context $context): void
    {
        //example: `product.manufacturer.media.name`
        $original = $fieldName;
        $prefix = $root . '.';

        if (strpos($fieldName, $prefix) === 0) {
            $fieldName = substr($fieldName, \strlen($prefix));
        }

        /** @var EntityDefinition $definition */
        $fields = $definition::getFields();

        if (!$fields->has($fieldName)) {
            $associationKey = explode('.', $fieldName);
            $fieldName = array_shift($associationKey);
        }

        if (!$fields->has($fieldName)) {
            return;
        }

        $field = $fields->get($fieldName);

        if (!$field) {
            return;
        }

        /** @var AssociationInterface|Field $field */
        $field = $fields->get($fieldName);

        $this->fieldResolverRegistry->resolve($definition, $root, $field, $query, $context, $this);

        if (!$field instanceof AssociationInterface) {
            return;
        }

        $referenceClass = $field->getReferenceClass();
        if ($field instanceof ManyToManyAssociationField) {
            $referenceClass = $field->getReferenceDefinition();
        }

        $this->resolveAccessor(
            $original,
            $referenceClass,
            $root . '.' . $field->getPropertyName(),
            $query,
            $context
        );
    }

    public function resolveField(Field $field, string $definition, string $root, QueryBuilder $query, Context $context, bool $raw = false): void
    {
        $this->fieldResolverRegistry->resolve($definition, $root, $field, $query, $context, $this, $raw);
    }

    /**
     * Adds the full translation select part to the provided sql query.
     * Considers the parent-child inheritance and provided context language inheritance.
     * The raw parameter allows to skip the parent-child inheritance.
     */
    public function addTranslationSelect(string $root, string $definition, QueryBuilder $query, Context $context, array $fields, bool $raw = false): void
    {
        $fields = array_values($fields);
        $this->resolveField($fields[0], $definition, $root, $query, $context, $raw);

        $chain = $this->buildTranslationChain($root, $definition, $context, $raw);

        /** @var TranslatedField $field */
        foreach ($fields as $property => $field) {
            /** @var Field|StorageAware $translatedField */
            $translatedField = $this->getTranslatedField($definition, $field);
            $query->addSelect(
                $this->getTranslationFieldAccessor($root, $translatedField->getStorageName(), $chain)
                . ' as ' .
                self::escape($root . '.' . $field->getPropertyName())
            );

            $select = self::escape($chain[0]) . '.' . self::escape($translatedField->getStorageName());

            /** @var string|EntityDefinition $definition */
            if ($definition::isInheritanceAware() && $field->is(Inherited::class) && !$raw) {
                $select = sprintf(
                    'COALESCE(%s)',
                    $select . ',' . self::escape($chain[1]) . '.' . self::escape($translatedField->getStorageName())
                );
            }

            $query->addSelect(
                $select . ' IS NOT NULL'
                . ' as ' .
                self::escape('_' . $root . '.' . $field->getPropertyName() . '.translated')
            );

            $query->addSelect(
                self::escape($chain[0]) . '.' . self::escape($translatedField->getStorageName()) . ' IS NULL'
                . ' as ' .
                self::escape('_' . $root . '.' . $field->getPropertyName() . '.inherited')
            );
        }
    }

    public function joinVersion(QueryBuilder $query, string $definition, string $root, Context $context): void
    {
        /** @var string|EntityDefinition $definition */
        $table = $definition::getEntityName();

        $connection = $query->getConnection();
        $versionQuery = $connection->createQueryBuilder();
        $versionQuery->select([
            'COALESCE(draft.`id`, live.`id`) as id',
            'COALESCE(draft.`version_id`, live.`version_id`) as version_id',
        ]);
        $versionQuery->from(self::escape($table), 'live');
        $versionQuery->leftJoin('live', self::escape($table), 'draft', 'draft.`id` = live.`id` AND draft.`version_id` = :version');
        $versionQuery->andWhere('live.`version_id` = :liveVersion');

        $query->setParameter('liveVersion', Uuid::fromStringToBytes(Defaults::LIVE_VERSION));
        $query->setParameter('version', Uuid::fromStringToBytes($context->getVersionId()));

        $versionRoot = $root . '_version';

        $query->innerJoin(
            self::escape($root),
            '(' . $versionQuery->getSQL() . ')',
            self::escape($versionRoot),
            str_replace(
                ['#version#', '#root#'],
                [self::escape($versionRoot), self::escape($root)],
                '#version#.`version_id` = #root#.`version_id` AND #version#.`id` = #root#.`id`'
            )
        );
    }

    private function getTranslatedField(string $definition, TranslatedField $translatedField): Field
    {
        /** @var EntityDefinition|string $definition */
        $translationDefinition = $definition::getTranslationDefinitionClass();
        /** @var EntityDefinition|string $translationDefinition */
        $field = $translationDefinition::getFields()->get($translatedField->getPropertyName());
        if ($field === null || !$field instanceof StorageAware || !$field instanceof Field) {
            throw new \RuntimeException(\sprintf('Missing translated storage aware property %s in %s', $translatedField->getPropertyName(), $translationDefinition));
        }

        return $field;
    }

    private function getTranslationFieldAccessor(string $root, string $storageName, array $chain): string
    {
        $alias = $root . '.translation';
        if (\count($chain) === 1) {
            return self::escape($alias) . '.' . self::escape($storageName);
        }

        $chainSelect = [];
        foreach ($chain as $table) {
            $chainSelect[] = self::escape($table) . '.' . self::escape($storageName);
        }

        return sprintf('COALESCE(%s)', implode(',', $chainSelect));
    }

    private function buildTranslationChain(string $root, string $definition, Context $context, bool $raw = false): array
    {
        $chain = [$root . '.translation'];

        /** @var string|EntityDefinition $definition */
        if ($definition::isInheritanceAware() && !$raw) {
            /* @var EntityDefinition|string $definition */
            $chain[] = $root . '.parent.translation';
        }

        if ($context->hasFallback()) {
            $chain[] = $root . '.translation.fallback';
        }

        if ($definition::isInheritanceAware() && $context->hasFallback() && !$raw) {
            /* @var EntityDefinition|string $definition */
            $chain[] = $root . '.parent.translation.fallback';
        }

        return $chain;
    }

    private function buildInheritedAccessor(Field $field, string $root, string $definition, Context $context, string $original): string
    {
        /* @var string|EntityDefinition $definition */
        if ($field instanceof TranslatedField) {
            $inheritedChain = $this->buildTranslationChain($root, $definition, $context);
            /** @var Field|StorageAware $translatedField */
            $translatedField = $this->getTranslatedField($definition, $field);

            return $this->getTranslationFieldAccessor($root, $translatedField->getStorageName(), $inheritedChain);
        }

        $select = $this->buildFieldSelector($root, $field, $context, $original);

        if (!$field->is(Inherited::class)) {
            return $select;
        }

        $parentSelect = $this->buildFieldSelector(
            $root . '.parent',
            $field,
            $context,
            $original
        );

        return sprintf('IFNULL(%s, %s)', $select, $parentSelect);
    }

    private function buildFieldSelector(string $root, Field $field, Context $context, string $accessor): string
    {
        return $this->fieldAccessorBuilderRegistry->buildAccessor($root, $field, $context, $accessor);
    }
}
