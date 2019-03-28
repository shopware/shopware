<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;

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
        if (strpos($string, '`') !== false) {
            throw new \InvalidArgumentException('Backtick not allowed in identifier');
        }

        return '`' . $string . '`';
    }

    public static function getFieldsOfAccessor(string $definition, string $accessor): array
    {
        $parts = explode('.', $accessor);
        array_shift($parts);

        $accessorFields = [];

        /** @var string|EntityDefinition $source */
        $source = $definition;

        foreach ($parts as $part) {
            $fields = $source::getFields();

            if ($part === 'extensions') {
                continue;
            }
            $field = $fields->get($part);

            if ($field instanceof TranslatedField) {
                $source = $source::getTranslationDefinitionClass();
                $fields = $source::getFields();
                $accessorFields[] = $fields->get($part);
                continue;
            }

            $accessorFields[] = $field;

            if (!$field instanceof AssociationInterface) {
                break;
            }

            $source = $field->getReferenceClass();
            if ($field instanceof ManyToManyAssociationField) {
                $source = $field->getReferenceDefinition();
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
     *
     * @param string|EntityDefinition $definition
     */
    public function getField(string $fieldName, string $definition, string $root): ?Field
    {
        $original = $fieldName;
        $prefix = $root . '.';

        if (strpos($fieldName, $prefix) === 0) {
            $fieldName = substr($fieldName, \strlen($prefix));
        }

        $fields = $definition::getFields();

        $isAssociation = strpos($fieldName, '.') !== false;

        if (!$isAssociation && $fields->has($fieldName)) {
            return $fields->get($fieldName);
        }
        $associationKey = explode('.', $fieldName);
        $associationKey = array_shift($associationKey);

        $field = $fields->get($associationKey);

        if ($field instanceof TranslatedField) {
            return self::getTranslatedField($definition, $field);
        }

        if (!$field instanceof AssociationInterface) {
            return $field;
        }

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
     * @param string|EntityDefinition $definition
     *
     * @throws UnmappedFieldException
     */
    public function getFieldAccessor(string $fieldName, string $definition, string $root, Context $context): string
    {
        $fieldName = str_replace('extensions.', '', $fieldName);

        $original = $fieldName;
        $prefix = $root . '.';

        if (strpos($fieldName, $prefix) === 0) {
            $fieldName = substr($fieldName, \strlen($prefix));
        }

        $fields = $definition::getFields();
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

        /** @var AssociationInterface|Field $field */
        $field = $fields->get($associationKey);

        //case for json object fields, other fields has now same option to act with more point notations but hasn't to be an association field. E.g. price.gross
        if (!$field instanceof AssociationInterface && ($field instanceof StorageAware || $field instanceof TranslatedField)) {
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
     * It considers the current context version.
     *
     * @param string|EntityDefinition $definition
     */
    public function getBaseQuery(QueryBuilder $query, string $definition, Context $context): QueryBuilder
    {
        $table = $definition::getEntityName();

        $query->from(self::escape($table));

        $useVersionFallback = (
            // only applies for versioned entities
            $definition::isVersionAware()
            // only add live fallback if the current version isn't the live version
            && $context->getVersionId() !== Defaults::LIVE_VERSION
            // sub entities have no live fallback
            && $definition::getParentDefinitionClass() === null
        );

        if ($useVersionFallback) {
            $this->joinVersion($query, $definition, $definition::getEntityName(), $context);
        } elseif ($definition::isVersionAware()) {
            $versionIdField = array_filter(
                $definition::getPrimaryKeys(),
                function ($f) {
                    return $f instanceof VersionField || $f instanceof ReferenceVersionField;
                }
            );

            if (!$versionIdField) {
                throw new \RuntimeException('Missing `VersionField` in `' . $definition . '`');
            }

            /** @var FkField|null $versionIdField */
            $versionIdField = array_shift($versionIdField);

            $query->andWhere(self::escape($table) . '.' . self::escape($versionIdField->getStorageName()) . ' = :version');
            $query->setParameter('version', Uuid::fromStringToBytes($context->getVersionId()));
        }

        $this->addRuleCondition($query, $definition, $context);

        return $query;
    }

    public function buildRuleCondition(string $definition, QueryBuilder $query, string $alias, Context $context): ?string
    {
        $conditions = [];

        /** @var string|EntityDefinition $definition */
        if ($definition::isBlacklistAware() && $context->getRules()) {
            $accessor = self::escape($alias) . '.' . self::escape('blacklist_ids');

            $wheres = [];

            foreach ($context->getRules() as $ruleId) {
                if (!Uuid::isValid($ruleId)) {
                    throw new InvalidUuidException($ruleId);
                }
                $wheres[] = sprintf(
                    'JSON_CONTAINS(IFNULL(' . $accessor . ', JSON_ARRAY()), JSON_ARRAY(:%s))',
                    'contextRule' . $ruleId
                );
                $query->setParameter('contextRule' . $ruleId, $ruleId);
            }

            $conditions[] = implode(' + ', $wheres) . ' = 0';
        }

        if (!$definition::isWhitelistAware()) {
            return empty($conditions) ? null : implode(' AND ', $conditions);
        }

        $accessor = self::escape($alias) . '.' . self::escape('whitelist_ids');

        $whitelistConditions = [
            'JSON_DEPTH(' . $accessor . ') is null',
            'JSON_DEPTH(' . $accessor . ') = 1',
        ];

        $wheres = [];
        foreach ($context->getRules() as $ruleId) {
            if (!Uuid::isValid($ruleId)) {
                throw new InvalidUuidException($ruleId);
            }
            $wheres[] = sprintf(
                'JSON_CONTAINS(IFNULL(' . $accessor . ', JSON_ARRAY()), JSON_ARRAY(:%s))',
                'contextRule' . $ruleId
            );
            $query->setParameter('contextRule' . $ruleId, $ruleId);
        }

        if (!empty($wheres)) {
            $whitelistConditions[] = implode(' + ', $wheres) . ' >= 1';
        }

        $conditions[] = '(' . implode(' OR ', $whitelistConditions) . ')';

        return empty($conditions) ? null : implode(' AND ', $conditions);
    }

    /**
     * Used for dynamic sql joins. In case that the given fieldName is unknown or event nested with multiple association
     * roots, the function can resolve each association part of the field name, even if one part of the fieldName contains a translation or event inherited data field.
     *
     * @param string|EntityDefinition $definition
     */
    public function resolveAccessor(string $fieldName, string $definition, string $root, QueryBuilder $query, Context $context): void
    {
        $fieldName = str_replace('extensions.', '', $fieldName);

        //example: `product.manufacturer.media.name`
        $original = $fieldName;
        $prefix = $root . '.';

        if (strpos($fieldName, $prefix) === 0) {
            $fieldName = substr($fieldName, \strlen($prefix));
        }

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

    public function resolveField(Field $field, string $definition, string $root, QueryBuilder $query, Context $context): void
    {
        $this->fieldResolverRegistry->resolve($definition, $root, $field, $query, $context, $this);
    }

    /**
     * Adds the full translation select part to the provided sql query.
     * Considers the parent-child inheritance and provided context language inheritance.
     * The raw parameter allows to skip the parent-child inheritance.
     *
     * @param string|EntityDefinition $definition
     */
    public function addTranslationSelect(string $root, string $definition, QueryBuilder $query, Context $context): void
    {
        /** @var string|EntityDefinition $translationDefinition */
        $translationDefinition = $definition::getTranslationDefinitionClass();

        $fields = $translationDefinition::getFields();
        $chain = self::buildTranslationChain($root, $context, $definition::isInheritanceAware());

        /** @var TranslatedField $field */
        foreach ($fields as $field) {
            if (!$field instanceof StorageAware) {
                continue;
            }

            foreach ($chain as $tableAccessor) {
                $name = $field->getPropertyName();
                $query->addSelect(
                    self::escape($tableAccessor['alias']) . '.' . self::escape($field->getStorageName()) . ' as '
                    . self::escape($tableAccessor['alias'] . '.' . $name)
                );
            }

            //check if current field is a translated field of the origin definition
            $origin = $definition::getFields()->get($field->getPropertyName());
            if ($origin instanceof TranslatedField) {
                //add selection for resolved parent-child and language inheritance
                $query->addSelect(
                    $this->getTranslationFieldSelectExpr($field, $chain) . ' as '
                    . self::escape($root . '.' . $field->getPropertyName())
                );
            }
        }
    }

    /**
     * @param string|EntityDefinition $definition
     */
    public function joinVersion(QueryBuilder $query, string $definition, string $root, Context $context): void
    {
        $table = $definition::getEntityName();

        $versionQuery = $query->getConnection()->createQueryBuilder();
        $versionQuery->select([
            'DISTINCT COALESCE(draft.`id`, live.`id`) as id',
            'COALESCE(draft.`version_id`, live.`version_id`) as version_id',
        ]);
        $versionQuery->from(self::escape($table), 'live');
        $versionQuery->leftJoin('live', self::escape($table), 'draft', 'draft.`id` = live.`id` AND draft.`version_id` = :version');
        $versionQuery->andWhere('live.`version_id` = :liveVersion OR draft.version_id = :version');

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

    /**
     * @param string|EntityDefinition $definition
     */
    public static function getTranslatedField(string $definition, TranslatedField $translatedField): Field
    {
        $translationDefinition = $definition::getTranslationDefinitionClass();
        $field = $translationDefinition::getFields()->get($translatedField->getPropertyName());
        if ($field === null || !$field instanceof StorageAware || !$field instanceof Field) {
            throw new \RuntimeException(\sprintf('Missing translated storage aware property %s in %s', $translatedField->getPropertyName(), $translationDefinition));
        }

        return $field;
    }

    public static function buildTranslationChain(string $root, Context $context, bool $includeParent): array
    {
        // the first one is the most specify and always selected
        $idChain = $context->getLanguageIdChain();
        $id = array_shift($idChain);

        $chain = [[
            'id' => $id,
            'name' => 'translation',
            'alias' => $root . '.translation',
            'root' => $root,
        ]];
        if ($includeParent) {
            $chain[] = [
                'id' => $id,
                'name' => 'parent.translation',
                'alias' => $root . '.parent.translation',
                'root' => $root . '.parent',
            ];
        }

        $i = 1;
        foreach ($idChain as $id) {
            $name = 'translation.fallback_' . $i++;
            $chain[] = [
                'id' => $id,
                'name' => $name,
                'alias' => $root . '.' . $name,
                'root' => $root,
            ];
            if ($includeParent) {
                $chain[] = [
                    'id' => $id,
                    'name' => 'parent.' . $name,
                    'alias' => $root . '.parent.' . $name,
                    'root' => $root . '.parent',
                ];
            }
        }

        return $chain;
    }

    /**
     * Adds a blacklist and whitelist where condition to the provided query.
     * This function is only for internal usage for the root entity of the query.
     */
    private function addRuleCondition(QueryBuilder $query, string $definition, Context $context): void
    {
        /** @var string|EntityDefinition $definition */
        if ($definition::isBlacklistAware() && $context->getRules()) {
            $wheres = [];

            $accessor = $this->getFieldAccessor('blacklistIds', $definition, $definition::getEntityName(), $context);

            foreach ($context->getRules() as $ruleId) {
                if (!Uuid::isValid($ruleId)) {
                    throw new InvalidUuidException($ruleId);
                }
                $wheres[] = sprintf(
                    'JSON_CONTAINS(IFNULL(' . $accessor . ', JSON_ARRAY()), JSON_ARRAY(:%s))',
                    'contextRule' . $ruleId
                );
                $query->setParameter('contextRule' . $ruleId, $ruleId);
            }

            $query->andWhere(implode(' + ', $wheres) . ' = 0');
        }

        if (!$definition::isWhitelistAware()) {
            return;
        }

        $accessor = $this->getFieldAccessor('whitelistIds', $definition, $definition::getEntityName(), $context);

        $wheres = [];
        foreach ($context->getRules() as $id) {
            if (!Uuid::isValid($id)) {
                throw new InvalidUuidException($id);
            }
            $wheres[] = sprintf(
                'JSON_CONTAINS(IFNULL(' . $accessor . ', JSON_ARRAY()), JSON_ARRAY(:%s))',
                'contextRule' . $id
            );
            $query->setParameter('contextRule' . $id, $id);
        }

        $conditions = [
            '(JSON_DEPTH(' . $accessor . ') is null)',
            '(JSON_DEPTH(' . $accessor . ') = 1)',
        ];

        if (!empty($wheres)) {
            $conditions[] = implode(' + ', $wheres) . ' >= 1';
        }

        $query->andWhere('(' . implode(' OR ', $conditions) . ')');
    }

    private function getTranslationFieldSelectExpr(StorageAware $field, array $chain): string
    {
        if (\count($chain) === 1) {
            return self::escape($chain[0]['alias']) . '.' . self::escape($field->getStorageName());
        }

        $chainSelect = [];
        foreach ($chain as $part) {
            $chainSelect[] = self::escape($part['alias']) . '.' . self::escape($field->getStorageName());
        }

        return sprintf('COALESCE(%s)', implode(',', $chainSelect));
    }

    private function getTranslationFieldAccessor(Field $field, string $accessor, array $chain, Context $context): string
    {
        $sqlExps = [];
        foreach ($chain as $part) {
            $sqlExps[] = $this->buildFieldSelector(
                $part['alias'],
                $field,
                $context,
                $accessor
            );
        }

        /*
         * Simplified Example:
         * COALESCE(
             JSON_UNQUOTE(JSON_EXTRACT(`tbl.translation`.`translated_attributes`, '$.path')) AS datetime(3), # child language
             JSON_UNQUOTE(JSON_EXTRACT(`tbl.translation.fallback_1`.`translated_attributes`, '$.path')) AS datetime(3), # root language
             JSON_UNQUOTE(JSON_EXTRACT(`tbl.translation.fallback_2`.`translated_attributes`, '$.path')) AS datetime(3) # system language
           );
         */
        return sprintf('COALESCE(%s)', implode(',', $sqlExps));
    }

    private function buildInheritedAccessor(Field $field, string $root, string $definition, Context $context, string $original): string
    {
        /* @var string|EntityDefinition $definition */
        if ($field instanceof TranslatedField) {
            $inheritedChain = self::buildTranslationChain($root, $context, $definition::isInheritanceAware());
            /** @var Field|StorageAware $translatedField */
            $translatedField = self::getTranslatedField($definition, $field);

            return $this->getTranslationFieldAccessor($translatedField, $original, $inheritedChain, $context);
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
