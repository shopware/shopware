<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * This class acts only as helper/common class for all dbal operations for entity definitions.
 * It knows how an association should be joined, how a parent-child inheritance should act, how translation chains work, ...
 */
class EntityDefinitionQueryHelper
{
    public const HAS_TO_MANY_JOIN = 'has_to_many_join';

    public static function escape(string $string): string
    {
        if (mb_strpos($string, '`') !== false) {
            throw new \InvalidArgumentException('Backtick not allowed in identifier');
        }

        return '`' . $string . '`';
    }

    public static function getFieldsOfAccessor(EntityDefinition $definition, string $accessor, bool $resolveTranslated = true): array
    {
        $parts = explode('.', $accessor);
        if ($definition->getEntityName() === $parts[0]) {
            array_shift($parts);
        }

        $accessorFields = [];

        $source = $definition;

        foreach ($parts as $part) {
            $fields = $source->getFields();

            if ($part === 'extensions') {
                continue;
            }
            $field = $fields->get($part);

            if ($field instanceof TranslatedField && $resolveTranslated) {
                $source = $source->getTranslationDefinition();
                $fields = $source->getFields();
                $accessorFields[] = $fields->get($part);

                continue;
            }

            if ($field instanceof TranslatedField && !$resolveTranslated) {
                $accessorFields[] = $field;

                continue;
            }

            $accessorFields[] = $field;

            if (!$field instanceof AssociationField) {
                break;
            }

            $source = $field->getReferenceDefinition();
            if ($field instanceof ManyToManyAssociationField) {
                $source = $field->getToManyReferenceDefinition();
            }
        }

        return $accessorFields;
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
    public function getField(string $fieldName, EntityDefinition $definition, string $root, bool $resolveTranslated = true): ?Field
    {
        $original = $fieldName;
        $prefix = $root . '.';

        if (mb_strpos($fieldName, $prefix) === 0) {
            $fieldName = mb_substr($fieldName, mb_strlen($prefix));
        } else {
            $original = $prefix . $original;
        }

        $fields = $definition->getFields();

        $isAssociation = mb_strpos($fieldName, '.') !== false;

        if (!$isAssociation && $fields->has($fieldName)) {
            return $fields->get($fieldName);
        }
        $associationKey = explode('.', $fieldName);
        $associationKey = array_shift($associationKey);

        $field = $fields->get($associationKey);

        if ($field instanceof TranslatedField && $resolveTranslated) {
            return self::getTranslatedField($definition, $field);
        }
        if ($field instanceof TranslatedField) {
            return $field;
        }

        if (!$field instanceof AssociationField) {
            return $field;
        }

        $referenceDefinition = $field->getReferenceDefinition();
        if ($field instanceof ManyToManyAssociationField) {
            $referenceDefinition = $field->getToManyReferenceDefinition();
        }

        return $this->getField(
            $original,
            $referenceDefinition,
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
    public function getFieldAccessor(string $fieldName, EntityDefinition $definition, string $root, Context $context): string
    {
        $fieldName = str_replace('extensions.', '', $fieldName);

        $original = $fieldName;
        $prefix = $root . '.';

        if (mb_strpos($fieldName, $prefix) === 0) {
            $fieldName = mb_substr($fieldName, mb_strlen($prefix));
        } else {
            $original = $prefix . $original;
        }

        $fields = $definition->getFields();
        if ($fields->has($fieldName)) {
            $field = $fields->get($fieldName);

            return $this->buildInheritedAccessor($field, $root, $definition, $context, $fieldName);
        }

        $parts = explode('.', $fieldName);
        $associationKey = array_shift($parts);

        if ($associationKey === 'extensions') {
            $associationKey = array_shift($parts);
        }

        if (!$fields->has($associationKey)) {
            throw new UnmappedFieldException($original, $definition);
        }

        $field = $fields->get($associationKey);

        //case for json object fields, other fields has now same option to act with more point notations but hasn't to be an association field. E.g. price.gross
        if (!$field instanceof AssociationField && ($field instanceof StorageAware || $field instanceof TranslatedField)) {
            return $this->buildInheritedAccessor($field, $root, $definition, $context, $fieldName);
        }

        if (!$field instanceof AssociationField) {
            throw new \RuntimeException(sprintf('Expected field "%s" to be instance of %s', $associationKey, AssociationField::class));
        }

        $referenceDefinition = $field->getReferenceDefinition();
        if ($field instanceof ManyToManyAssociationField) {
            $referenceDefinition = $field->getToManyReferenceDefinition();
        }

        return $this->getFieldAccessor(
            $original,
            $referenceDefinition,
            $root . '.' . $field->getPropertyName(),
            $context
        );
    }

    /**
     * Creates the basic root query for the provided entity definition and application context.
     * It considers the current context version.
     */
    public function getBaseQuery(QueryBuilder $query, EntityDefinition $definition, Context $context): QueryBuilder
    {
        $table = $definition->getEntityName();

        $query->from(self::escape($table));

        $useVersionFallback // only applies for versioned entities
            = $definition->isVersionAware()
            // only add live fallback if the current version isn't the live version
            && $context->getVersionId() !== Defaults::LIVE_VERSION
            // sub entities have no live fallback
            && $definition->getParentDefinition() === null;

        if ($useVersionFallback) {
            $this->joinVersion($query, $definition, $definition->getEntityName(), $context);
        } elseif ($definition->isVersionAware()) {
            $versionIdField = array_filter(
                $definition->getPrimaryKeys()->getElements(),
                function ($f) {
                    return $f instanceof VersionField || $f instanceof ReferenceVersionField;
                }
            );

            if (!$versionIdField) {
                throw new \RuntimeException('Missing `VersionField` in `' . $definition->getClass() . '`');
            }

            /** @var FkField|null $versionIdField */
            $versionIdField = array_shift($versionIdField);

            $query->andWhere(self::escape($table) . '.' . self::escape($versionIdField->getStorageName()) . ' = :version');
            $query->setParameter('version', Uuid::fromHexToBytes($context->getVersionId()));
        }

        return $query;
    }

    /**
     * Used for dynamic sql joins. In case that the given fieldName is unknown or event nested with multiple association
     * roots, the function can resolve each association part of the field name, even if one part of the fieldName contains a translation or event inherited data field.
     */
    public function resolveAccessor(
        string $accessor,
        EntityDefinition $definition,
        string $root,
        QueryBuilder $query,
        Context $context,
        ?CriteriaPartInterface $criteriaPart = null
    ): void {
        $accessor = str_replace('extensions.', '', $accessor);

        $parts = explode('.', $accessor);
        if (empty($parts)) {
            return;
        }

        if ($parts[0] === $root) {
            unset($parts[0]);
        }

        $alias = $root;

        $path = [$root];

        $rootDefinition = $definition;

        foreach ($parts as $part) {
            $field = $definition->getFields()->get($part);

            if ($field === null) {
                return;
            }

            $resolver = $field->getResolver();
            if ($resolver === null) {
                continue;
            }

            if ($field instanceof AssociationField) {
                $path[] = $field->getPropertyName();
            }

            $currentPath = implode('.', $path);
            $resolverContext = new FieldResolverContext($currentPath, $alias, $field, $definition, $rootDefinition, $query, $context, $criteriaPart);

            $alias = $this->callResolver($resolverContext);

            if (!$field instanceof AssociationField) {
                return;
            }

            $definition = $field->getReferenceDefinition();
            if ($field instanceof ManyToManyAssociationField) {
                $definition = $field->getToManyReferenceDefinition();
            }

            if ($definition->isInheritanceAware() && $context->considerInheritance() && $parent = $definition->getField('parent')) {
                $resolverContext = new FieldResolverContext($currentPath, $alias, $parent, $definition, $rootDefinition, $query, $context, $criteriaPart);

                $this->callResolver($resolverContext);
            }
        }
    }

    public function resolveField(Field $field, EntityDefinition $definition, string $root, QueryBuilder $query, Context $context): void
    {
        $resolver = $field->getResolver();

        if ($resolver === null) {
            return;
        }

        $resolver->join(new FieldResolverContext($root, $root, $field, $definition, $definition, $query, $context, null));
    }

    /**
     * Adds the full translation select part to the provided sql query.
     * Considers the parent-child inheritance and provided context language inheritance.
     * The raw parameter allows to skip the parent-child inheritance.
     */
    public function addTranslationSelect(string $root, EntityDefinition $definition, QueryBuilder $query, Context $context): void
    {
        $translationDefinition = $definition->getTranslationDefinition();

        if (!$translationDefinition) {
            return;
        }

        $fields = $translationDefinition->getFields();

        $inherited = $context->considerInheritance() && $definition->isInheritanceAware();

        $chain = EntityDefinitionQueryHelper::buildTranslationChain($root, $context, $inherited);

        /** @var TranslatedField $field */
        foreach ($fields as $field) {
            if (!$field instanceof StorageAware) {
                continue;
            }

            $selects = [];
            foreach ($chain as $select) {
                $vars = [
                    '#root#' => $select,
                    '#field#' => $field->getPropertyName(),
                ];

                $query->addSelect(str_replace(
                    array_keys($vars),
                    array_values($vars),
                    EntityDefinitionQueryHelper::escape('#root#.#field#')
                ));

                $selects[] = str_replace(
                    array_keys($vars),
                    array_values($vars),
                    self::escape('#root#.#field#')
                );
            }

            //check if current field is a translated field of the origin definition
            $origin = $definition->getFields()->get($field->getPropertyName());
            if (!$origin instanceof TranslatedField) {
                continue;
            }

            $selects[] = self::escape($root . '.translation.' . $field->getPropertyName());

            //add selection for resolved parent-child and language inheritance
            $query->addSelect(
                sprintf('COALESCE(%s)', implode(',', $selects)) . ' as '
                . self::escape($root . '.' . $field->getPropertyName())
            );
        }
    }

    public function joinVersion(QueryBuilder $query, EntityDefinition $definition, string $root, Context $context): void
    {
        $table = $definition->getEntityName();

        $versionQuery = $query->getConnection()->createQueryBuilder();
        $versionQuery->select([
            'DISTINCT COALESCE(draft.`id`, live.`id`) as id',
            'COALESCE(draft.`version_id`, live.`version_id`) as version_id',
        ]);
        $versionQuery->from(self::escape($table), 'live');
        $versionQuery->leftJoin('live', self::escape($table), 'draft', 'draft.`id` = live.`id` AND draft.`version_id` = :version');
        $versionQuery->andWhere('live.`version_id` = :liveVersion OR draft.version_id = :version');

        $query->setParameter('liveVersion', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));
        $query->setParameter('version', Uuid::fromHexToBytes($context->getVersionId()));

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

    public static function getTranslatedField(EntityDefinition $definition, TranslatedField $translatedField): Field
    {
        $translationDefinition = $definition->getTranslationDefinition();

        if ($translationDefinition === null) {
            throw new \RuntimeException(sprintf('Entity %s has no translation definition', $definition->getEntityName()));
        }

        $field = $translationDefinition->getFields()->get($translatedField->getPropertyName());

        if ($field === null || !$field instanceof StorageAware || !$field instanceof Field) {
            throw new \RuntimeException(
                sprintf(
                    'Missing translated storage aware property %s in %s',
                    $translatedField->getPropertyName(),
                    $translationDefinition->getClass()
                )
            );
        }

        return $field;
    }

    public static function buildTranslationChain(string $root, Context $context, bool $includeParent): array
    {
        $count = \count($context->getLanguageIdChain()) - 1;

        for ($i = $count; $i >= 1; --$i) {
            $chain[] = $root . '.translation.fallback_' . $i;
            if ($includeParent) {
                $chain[] = $root . '.parent.translation.fallback_' . $i;
            }
        }

        $chain[] = $root . '.translation';
        if ($includeParent) {
            $chain[] = $root . '.parent.translation';
        }

        return $chain;
    }

    public function addIdCondition(Criteria $criteria, EntityDefinition $definition, QueryBuilder $query): void
    {
        $primaryKeys = $criteria->getIds();

        $primaryKeys = array_values($primaryKeys);

        if (empty($primaryKeys)) {
            return;
        }

        if (!\is_array($primaryKeys[0]) || \count($primaryKeys[0]) === 1) {
            $primaryKeyField = $definition->getPrimaryKeys()->first();
            /** @feature-deprecated (flag:FEATURE_NEXT_14872) remove FeatureCheck
             * if ($primaryKeyField instanceof IdField || $primaryKeyField instanceof FkField) {
             */
            if ($primaryKeyField instanceof IdField || (Feature::isActive('FEATURE_NEXT_14872') && $primaryKeyField instanceof FkField)) {
                $primaryKeys = array_map(function ($id) {
                    if (\is_array($id)) {
                        /** @var string $shiftedId */
                        $shiftedId = array_shift($id);

                        return Uuid::fromHexToBytes($shiftedId);
                    }

                    return Uuid::fromHexToBytes($id);
                }, $primaryKeys);
            }

            if (!$primaryKeyField instanceof StorageAware) {
                throw new \RuntimeException('Primary key fields has to be an instance of StorageAware');
            }

            $query->andWhere(sprintf(
                '%s.%s IN (:ids)',
                EntityDefinitionQueryHelper::escape($definition->getEntityName()),
                EntityDefinitionQueryHelper::escape($primaryKeyField->getStorageName())
            ));

            $query->setParameter('ids', array_values($primaryKeys), Connection::PARAM_STR_ARRAY);

            return;
        }

        $this->addIdConditionWithOr($criteria, $definition, $query);
    }

    private function callResolver(FieldResolverContext $context): string
    {
        $resolver = $context->getField()->getResolver();

        if (!$resolver) {
            return $context->getAlias();
        }

        return $resolver->join($context);
    }

    private function addIdConditionWithOr(Criteria $criteria, EntityDefinition $definition, QueryBuilder $query): void
    {
        $wheres = [];

        foreach ($criteria->getIds() as $primaryKey) {
            if (!\is_array($primaryKey)) {
                $primaryKey = ['id' => $primaryKey];
            }

            $where = [];

            foreach ($primaryKey as $storageName => $value) {
                $field = $definition->getFields()->getByStorageName($storageName);

                if ($field instanceof IdField || $field instanceof FkField) {
                    $value = Uuid::fromHexToBytes($value);
                }

                $key = 'pk' . Uuid::randomHex();

                $accessor = EntityDefinitionQueryHelper::escape($definition->getEntityName()) . '.' . EntityDefinitionQueryHelper::escape($storageName);

                $where[] = $accessor . ' = :' . $key;

                $query->setParameter($key, $value);
            }

            $wheres[] = '(' . implode(' AND ', $where) . ')';
        }

        $wheres = implode(' OR ', $wheres);

        $query->andWhere($wheres);
    }

    private function getTranslationFieldAccessor(Field $field, string $accessor, array $chain, Context $context): string
    {
        if (!$field instanceof StorageAware) {
            throw new \RuntimeException('Only storage aware fields are supported as translated field');
        }

        $selects = [];
        foreach ($chain as $part) {
            $select = $this->buildFieldSelector($part, $field, $context, $accessor);

            $selects[] = str_replace(
                '`.' . self::escape($field->getStorageName()),
                '.' . $field->getPropertyName() . '`',
                $select
            );
        }

        /*
         * Simplified Example:
         * COALESCE(
             JSON_UNQUOTE(JSON_EXTRACT(`tbl.translation.fallback_2`.`translated_attributes`, '$.path')) AS datetime(3), # child language
             JSON_UNQUOTE(JSON_EXTRACT(`tbl.translation.fallback_1`.`translated_attributes`, '$.path')) AS datetime(3), # root language
             JSON_UNQUOTE(JSON_EXTRACT(`tbl.translation`.`translated_attributes`, '$.path')) AS datetime(3) # system language
           );
         */
        return sprintf('COALESCE(%s)', implode(',', $selects));
    }

    private function buildInheritedAccessor(
        Field $field,
        string $root,
        EntityDefinition $definition,
        Context $context,
        string $original
    ): string {
        if ($field instanceof TranslatedField) {
            $inheritedChain = self::buildTranslationChain($root, $context, $definition->isInheritanceAware() && $context->considerInheritance());

            $translatedField = self::getTranslatedField($definition, $field);

            return $this->getTranslationFieldAccessor($translatedField, $original, $inheritedChain, $context);
        }

        $select = $this->buildFieldSelector($root, $field, $context, $original);

        if (!$field->is(Inherited::class) || !$context->considerInheritance()) {
            return $select;
        }

        $parentSelect = $this->buildFieldSelector($root . '.parent', $field, $context, $original);

        return sprintf('IFNULL(%s, %s)', $select, $parentSelect);
    }

    private function buildFieldSelector(string $root, Field $field, Context $context, string $accessor): string
    {
        return $field->getAccessorBuilder()->buildAccessor($root, $field, $context, $accessor);
    }
}
