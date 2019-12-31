<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder\AntiJoinBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder\AntiJoinInfo;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder\JoinBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * This class acts only as helper/common class for all dbal operations for entity definitions.
 * It knows how an association should be joined, how a parent-child inheritance should act, how translation chains work, ...
 */
class EntityDefinitionQueryHelper
{
    public const HAS_TO_MANY_JOIN = 'has_to_many_join';

    /**
     * @var AntiJoinBuilder
     */
    private $antiJoinBuilder;

    public function __construct(AntiJoinBuilder $antiJoinBuilder)
    {
        $this->antiJoinBuilder = $antiJoinBuilder;
    }

    public static function escape(string $string): string
    {
        if (mb_strpos($string, '`') !== false) {
            throw new \InvalidArgumentException('Backtick not allowed in identifier');
        }

        return '`' . $string . '`';
    }

    public static function getFieldsOfAccessor(EntityDefinition $definition, string $accessor): array
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

            if ($field instanceof TranslatedField) {
                $source = $source->getTranslationDefinition();
                $fields = $source->getFields();
                $accessorFields[] = $fields->get($part);

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
    public function getField(string $fieldName, EntityDefinition $definition, string $root): ?Field
    {
        $original = $fieldName;
        $prefix = $root . '.';

        if (mb_strpos($fieldName, $prefix) === 0) {
            $fieldName = mb_substr($fieldName, \mb_strlen($prefix));
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

        if ($field instanceof TranslatedField) {
            return self::getTranslatedField($definition, $field);
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
            $fieldName = mb_substr($fieldName, \mb_strlen($prefix));
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

        $useVersionFallback = (
            // only applies for versioned entities
            $definition->isVersionAware()
            // only add live fallback if the current version isn't the live version
            && $context->getVersionId() !== Defaults::LIVE_VERSION
            // sub entities have no live fallback
            && $definition->getParentDefinition() === null
        );

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

        $this->addRuleCondition($query, $definition, $context);

        return $query;
    }

    public function buildRuleCondition(EntityDefinition $definition, QueryBuilder $query, string $alias, Context $context): ?string
    {
        $ids = $context->getRuleIds();

        $conditions = [];

        if ($definition->isBlacklistAware() && $ids) {
            $accessor = self::escape($alias) . '.`blacklist_ids`';

            $param = '(' . implode('|', $ids) . ')';

            $conditions[] = '(NOT (' . $accessor . ' REGEXP :rules) OR ' . $accessor . ' IS NULL)';

            $query->setParameter('rules', $param);
        }

        if ($definition->isWhitelistAware() && $ids) {
            $accessor = self::escape($alias) . '.`whitelist_ids`';

            $param = '(' . implode('|', $ids) . ')';

            $conditions[] = '(' . $accessor . ' REGEXP :rules OR ' . $accessor . ' IS NULL)';

            $query->setParameter('rules', $param);
        } elseif ($definition->isWhitelistAware()) {
            $accessor = self::escape($alias) . '.`whitelist_ids`';

            $conditions[] = $accessor . ' IS NULL';
        }

        if (empty($conditions)) {
            return null;
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Used for dynamic sql joins. In case that the given fieldName is unknown or event nested with multiple association
     * roots, the function can resolve each association part of the field name, even if one part of the fieldName contains a translation or event inherited data field.
     */
    public function resolveAccessor(
        string $fieldName,
        EntityDefinition $definition,
        string $root,
        QueryBuilder $query,
        Context $context
    ): void {
        $fieldName = str_replace('extensions.', '', $fieldName);

        //example: `product.manufacturer.media.name`
        $original = $fieldName;
        $prefix = $root . '.';

        if (mb_strpos($fieldName, $prefix) === 0) {
            $fieldName = mb_substr($fieldName, \mb_strlen($prefix));
        } else {
            $original = $prefix . $original;
        }

        $fields = $definition->getFields();

        if (!$fields->has($fieldName)) {
            $associationKey = explode('.', $fieldName);
            $fieldName = array_shift($associationKey);
        }

        if (!$fields->has($fieldName)) {
            return;
        }

        /** @var AssociationField|null $field */
        $field = $fields->get($fieldName);

        if ($field === null) {
            return;
        }

        $resolver = $field->getResolver();

        if ($resolver !== null) {
            $resolver->resolve($definition, $root, $field, $query, $context, $this);
        }

        if (!$field instanceof AssociationField) {
            return;
        }

        $referenceDefinition = $field->getReferenceDefinition();
        if ($field instanceof ManyToManyAssociationField) {
            $referenceDefinition = $field->getToManyReferenceDefinition();
        }

        $this->resolveAccessor(
            $original,
            $referenceDefinition,
            $root . '.' . $field->getPropertyName(),
            $query,
            $context
        );
    }

    public function resolveAntiJoinAccessors(
        string $fieldName,
        EntityDefinition $definition,
        string $root,
        QueryBuilder $parentQueryBuilder,
        Context $context,
        array $antiJoinConditions = []
    ): void {
        foreach ($antiJoinConditions as $antiJoinIdentifier => $antiJoinCondition) {
            $select = $this->getFieldAccessor($fieldName, $definition, $root, $context);
            [$alias, $field] = explode('`.`', $select);
            $alias = ltrim($alias, '`');

            $selectField = EntityDefinitionQueryHelper::escape($alias) . '.`' . $field;

            $associations = $this->getAssociations($fieldName, $definition, $root);
            $antiJoinInfo = new AntiJoinInfo($associations, $antiJoinCondition, [$selectField]);

            $this->antiJoinBuilder->join(
                $definition,
                JoinBuilderInterface::LEFT_JOIN,
                $antiJoinInfo,
                $root,
                $alias . '_' . $antiJoinIdentifier,
                $parentQueryBuilder,
                $context
            );
        }
    }

    public function resolveField(Field $field, EntityDefinition $definition, string $root, QueryBuilder $query, Context $context): void
    {
        $resolver = $field->getResolver();

        if ($resolver === null) {
            return;
        }

        $resolver->resolve($definition, $root, $field, $query, $context, $this);
    }

    /**
     * Adds the full translation select part to the provided sql query.
     * Considers the parent-child inheritance and provided context language inheritance.
     * The raw parameter allows to skip the parent-child inheritance.
     */
    public function addTranslationSelect(
        string $root,
        EntityDefinition $definition,
        QueryBuilder $query,
        Context $context
    ): void {
        $translationDefinition = $definition->getTranslationDefinition();

        $fields = $translationDefinition->getFields();
        $chain = self::buildTranslationChain($root, $context, $definition->isInheritanceAware() && $context->considerInheritance());

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
            $origin = $definition->getFields()->get($field->getPropertyName());
            if ($origin instanceof TranslatedField) {
                //add selection for resolved parent-child and language inheritance
                $query->addSelect(
                    $this->getTranslationFieldSelectExpr($field, $chain) . ' as '
                    . self::escape($root . '.' . $field->getPropertyName())
                );
            }
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
        $field = $translationDefinition->getFields()->get($translatedField->getPropertyName());

        if ($field === null || !$field instanceof StorageAware || !$field instanceof Field) {
            throw new \RuntimeException(
                \sprintf(
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

    private function getAssociations(string $fieldName, EntityDefinition $definition, string $root): array
    {
        $fieldName = str_replace('extensions.', '', $fieldName);

        //example: `product.manufacturer.media.name`
        $original = $fieldName;
        $prefix = $root . '.';

        if (mb_strpos($fieldName, $prefix) === 0) {
            $fieldName = mb_substr($fieldName, \mb_strlen($prefix));
        }

        $fields = $definition->getFields();

        if (!$fields->has($fieldName)) {
            $associationKey = explode('.', $fieldName);
            $fieldName = array_shift($associationKey);
        }

        if (!$fields->has($fieldName)) {
            return [];
        }

        /** @var AssociationField|null $field */
        $field = $fields->get($fieldName);

        if ($field === null || !$field instanceof AssociationField) {
            return [];
        }

        $referenceDefinition = $field->getReferenceDefinition();
        if ($field instanceof ManyToManyAssociationField) {
            $referenceDefinition = $field->getToManyReferenceDefinition();
        }

        return array_merge([$root => $field], $this->getAssociations($original, $referenceDefinition, $root . '.' . $field->getPropertyName()));
    }

    /**
     * Adds a blacklist and whitelist where condition to the provided query.
     * This function is only for internal usage for the root entity of the query.
     */
    private function addRuleCondition(QueryBuilder $query, EntityDefinition $definition, Context $context): void
    {
        $ids = $context->getRuleIds();

        if ($definition->isBlacklistAware() && $ids) {
            $accessor = self::escape($definition->getEntityName()) . '.`blacklist_ids`';

            if ($this->isInherited($definition, $definition->getFields()->get('blacklistIds'), $context)) {
                $accessor = sprintf(
                    'IFNULL(%s, %s)',
                    self::escape($definition->getEntityName()) . '.`blacklist_ids`',
                    self::escape($definition->getEntityName() . '.parent') . '.`blacklist_ids`'
                );
            }

            $param = '(' . implode('|', $ids) . ')';

            $query->andWhere('NOT ' . $accessor . ' REGEXP :rules OR ' . $accessor . ' IS NULL');

            $query->setParameter('rules', $param);
        }

        if ($definition->isWhitelistAware() && $ids) {
            $accessor = self::escape($definition->getEntityName()) . '.`whitelist_ids`';
            if ($this->isInherited($definition, $definition->getFields()->get('whitelistIds'), $context)) {
                $accessor = sprintf(
                    'IFNULL(%s, %s)',
                    self::escape($definition->getEntityName()) . '.`whitelist_ids`',
                    self::escape($definition->getEntityName() . '.parent') . '.`whitelist_ids`'
                );
            }

            $param = '(' . implode('|', $ids) . ')';

            $query->andWhere($accessor . ' REGEXP :rules OR ' . $accessor . ' IS NULL');

            $query->setParameter('rules', $param);
        } elseif ($definition->isWhitelistAware()) {
            $accessor = self::escape($definition->getEntityName()) . '.`whitelist_ids`';

            if ($this->isInherited($definition, $definition->getFields()->get('whitelistIds'), $context)) {
                $accessor = sprintf(
                    'IFNULL(%s, %s)',
                    self::escape($definition->getEntityName()) . '.`whitelist_ids`',
                    self::escape($definition->getEntityName() . '.parent') . '.`whitelist_ids`'
                );
            }

            $query->andWhere($accessor . ' IS NULL');
        }
    }

    private function isInherited(EntityDefinition $definition, Field $field, Context $context): bool
    {
        return $definition->isInheritanceAware() && $field->is(Inherited::class) && $context->considerInheritance();
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
            $sqlExps[] = $this->buildFieldSelector($part['alias'], $field, $context, $accessor);
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

    private function buildInheritedAccessor(
        Field $field,
        string $root,
        EntityDefinition $definition,
        Context $context,
        string $original
    ): string {
        if ($field instanceof TranslatedField) {
            $inheritedChain = self::buildTranslationChain($root, $context, $definition->isInheritanceAware() && $context->considerInheritance());
            /** @var Field|StorageAware $translatedField */
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
