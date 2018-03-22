<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\AssociationInterface;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Write\FieldAware\DbalJoinAware;
use Shopware\Api\Entity\Write\FieldAware\SqlParseAware;
use Shopware\Api\Entity\Write\FieldAware\StorageAware;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Inherited;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;

/**
 * This class acts only as helper/common class for all dbal operations for entity definitions.
 * It knows how an association should be joined, how a parent-child inheritance should act, how translation chains work, ...
 */
class EntityDefinitionQueryHelper
{
    public const REQUIRES_GROUP_BY = 'has_to_many_join';

    public static function escape(string $string): string
    {
        return '`' . $string . '`';
    }

    public static function getField(string $fieldName, string $definition, string $root): ?Field
    {
        $original = $fieldName;
        $prefix = $root . '.';

        if (strpos($fieldName, $prefix) === 0) {
            $fieldName = substr($fieldName, strlen($prefix));
        }

        /** @var EntityDefinition $definition */
        $fields = $definition::getFields();

        $isAssociation = strpos($fieldName, '.') !== false;

        if (!$isAssociation && $fields->has($fieldName)) {
            return $fields->get($fieldName);
        }
        $associationKey = explode('.', $fieldName);
        $associationKey = array_shift($associationKey);

        /** @var AssociationInterface|Field $field */
        $field = $fields->get($associationKey);

        $referenceClass = $field->getReferenceClass();
        if ($field instanceof ManyToManyAssociationField) {
            $referenceClass = $field->getReferenceDefinition();
        }

        return self::getField(
            $original,
            $referenceClass,
            implode('.', [$root, $field->getPropertyName()])
        );
    }

    public static function getFieldAccessor(string $fieldName, string $definition, string $root, ShopContext $context): string
    {
        $original = $fieldName;
        $prefix = $root . '.';

        if (strpos($fieldName, $prefix) === 0) {
            $fieldName = substr($fieldName, strlen($prefix));
        }

        /** @var EntityDefinition $definition */
        $fields = $definition::getFields();

        if ($fields->has($fieldName)) {
            $field = $fields->get($fieldName);

            if ($field instanceof TranslatedField) {
                [$chain, $inheritedChain] = self::buildTranslationChain($root, $definition, $context);

                return self::getTranslationFieldAccessor($root, $field, $chain, $inheritedChain);
            }

            if ($field instanceof StorageAware) {
                if ($field instanceof SqlParseAware) {
                    $select = $field->parse($root, $context);
                } else {
                    $select = implode('.', [
                        self::escape($root),
                        self::escape($field->getStorageName()),
                    ]);
                }

                if (!$field->is(Inherited::class)) {
                    return $select;
                }

                if ($field instanceof SqlParseAware) {
                    $parentSelect = $field->parse(
                        $root . '.' . $definition::getParentPropertyName(),
                        $context
                    );
                } else {
                    $parentSelect = implode('.', [
                        self::escape($root . '.' . $definition::getParentPropertyName()),
                        self::escape($field->getStorageName()),
                    ]);
                }

                return sprintf('IFNULL(%s, %s)', $select, $parentSelect);
            }
        }

        $associationKey = explode('.', $fieldName);
        $associationKey = array_shift($associationKey);

        if (!$fields->has($associationKey)) {
            throw new \RuntimeException(sprintf('Unmapped field %s for definition class %s', $original, $definition));
        }

        /** @var AssociationInterface|Field $field */
        $field = $fields->get($associationKey);

        $referenceClass = $field->getReferenceClass();
        if ($field instanceof ManyToManyAssociationField) {
            $referenceClass = $field->getReferenceDefinition();
        }

        return self::getFieldAccessor(
            $original,
            $referenceClass,
            implode('.', [$root, $field->getPropertyName()]),
            $context
        );
    }

    public static function getBaseQuery(Connection $connection, string $definition, ShopContext $context): QueryBuilder
    {
        /** @var string|EntityDefinition $definition */
        $table = $definition::getEntityName();

        $query = new QueryBuilder($connection);
        $query->from(self::escape($table), self::escape($table));

        if ($definition::isVersionAware() && $context->getVersionId() !== Defaults::LIVE_VERSION) {
            self::joinVersion($query, $definition, $definition::getEntityName(), $context);
        } elseif ($definition::isVersionAware()) {
            $query->andWhere(self::escape($table) . '.`version_id` = :version');
            $query->setParameter('version', Uuid::fromStringToBytes($context->getVersionId()));
        }

        if ($definition::isCatalogAware()) {
            $catalogIds = array_map(function (string $catalogId) {
                return Uuid::fromStringToBytes($catalogId);
            }, $context->getCatalogIds());

            $query->andWhere(self::escape($table) . '.`catalog_id` IN (:catalogIds)');
            $query->setParameter('catalogIds', $catalogIds, Connection::PARAM_STR_ARRAY);
        }

        return $query;
    }

    public static function joinField(string $fieldName, string $definition, string $root, QueryBuilder $query, ShopContext $context): void
    {
        $original = $fieldName;
        $prefix = $root . '.';

        if (strpos($fieldName, $prefix) === 0) {
            $fieldName = substr($fieldName, strlen($prefix));
        }

        /** @var EntityDefinition $definition */
        $fields = $definition::getFields();

        if ($fields->has($fieldName)) {
            $field = $fields->get($fieldName);

            if ($field instanceof TranslatedField) {
                self::joinTranslation($root, $definition, $query, $context);
            }

            return;
        }

        $associationKey = explode('.', $fieldName);
        $associationKey = array_shift($associationKey);

        if (!$fields->has($associationKey)) {
            return;
        }

        /** @var AssociationInterface|Field $field */
        $field = $fields->get($associationKey);

        if (!$field) {
            return;
        }

        $referenceClass = null;
        if ($field instanceof ManyToOneAssociationField) {
            self::joinManyToOne($definition, $root, $field, $query, $context);
            $referenceClass = $field->getReferenceClass();
        }

        if ($field instanceof OneToManyAssociationField) {
            self::joinOneToMany($definition, $root, $field, $query, $context);
            $query->addState(self::REQUIRES_GROUP_BY);
            $referenceClass = $field->getReferenceClass();
        }

        if ($field instanceof ManyToManyAssociationField) {
            self::joinManyToMany($definition, $root, $field, $query, $context);
            $query->addState(self::REQUIRES_GROUP_BY);
            $referenceClass = $field->getReferenceDefinition();
        }

        if ($referenceClass === null) {
            throw new \RuntimeException(
                sprintf('Reference class can not be detected for association %s', get_class($field))
            );
        }

        self::joinField(
            $original,
            $referenceClass,
            implode('.', [$root, $field->getPropertyName()]),
            $query,
            $context
        );
    }

    /**
     * @param string|EntityDefinition   $definition
     * @param string                    $root
     * @param ManyToOneAssociationField $field
     * @param QueryBuilder              $query
     */
    public static function joinManyToOne(string $definition, string $root, ManyToOneAssociationField $field, QueryBuilder $query, ShopContext $context): void
    {
        /** @var EntityDefinition|string $reference */
        $reference = $field->getReferenceClass();
        $table = $reference::getEntityName();
        $alias = $root . '.' . $field->getPropertyName();

        if ($query->hasState($alias)) {
            return;
        }
        $query->addState($alias);

        $catalogJoinCondition = '';
        if ($definition::isCatalogAware() && $reference::isCatalogAware()) {
            $catalogJoinCondition = ' AND #root#.catalog_id = #alias#.catalog_id';
        }

        $versionAware = ($definition::isVersionAware() && $reference::isVersionAware());

        if ($field instanceof DbalJoinAware) {
            $field->join($query, $root, $context);
        } elseif ($versionAware && $context->getVersionId() !== Defaults::LIVE_VERSION) {
            $subRoot = $field->getReferenceClass()::getEntityName();
            $versionQuery = new QueryBuilder($query->getConnection());
            $versionQuery->select(self::escape($subRoot) . '.*');
            $versionQuery->from(self::escape($subRoot), self::escape($subRoot));

            self::joinVersion($versionQuery, $field->getReferenceClass(), $subRoot, $context);

            $query->leftJoin(
                self::escape($root),
                '(' . $versionQuery->getSQL() . ')',
                self::escape($alias),
                str_replace(
                    ['#root#', '#source_column#', '#alias#', '#reference_column#'],
                    [
                        self::escape($root),
                        self::escape($field->getJoinField()),
                        self::escape($alias),
                        self::escape($field->getReferenceField()),
                    ],
                    '#root#.#source_column# = #alias#.#reference_column#' . $catalogJoinCondition
                )
            );
        } elseif ($versionAware) {
            $query->leftJoin(
                self::escape($root),
                self::escape($table),
                self::escape($alias),
                str_replace(
                    ['#root#', '#source_column#', '#alias#', '#reference_column#'],
                    [
                        self::escape($root),
                        self::escape($field->getJoinField()),
                        self::escape($alias),
                        self::escape($field->getReferenceField()),
                    ],
                    '#root#.#source_column# = #alias#.#reference_column# AND #root#.version_id = #alias#.version_id' . $catalogJoinCondition
                )
            );
        } else {
            $query->leftJoin(
                self::escape($root),
                self::escape($table),
                self::escape($alias),
                str_replace(
                    ['#root#', '#source_column#', '#alias#', '#reference_column#'],
                    [
                        self::escape($root),
                        self::escape($field->getJoinField()),
                        self::escape($alias),
                        self::escape($field->getReferenceField()),
                    ],
                    '#root#.#source_column# = #alias#.#reference_column#' . $catalogJoinCondition
                )
            );
        }

        if ($definition === $reference) {
            return;
        }

        if (!$reference::getParentPropertyName()) {
            return;
        }

        $parent = $reference::getFields()->get($reference::getParentPropertyName());
        self::joinManyToOne($reference, $alias, $parent, $query, $context);
    }

    public static function joinOneToMany(string $definition, string $root, OneToManyAssociationField $field, QueryBuilder $query, ShopContext $context): void
    {
        /** @var EntityDefinition|string $reference */
        $reference = $field->getReferenceClass();

        $table = $reference::getEntityName();

        $alias = $root . '.' . $field->getPropertyName();
        if ($query->hasState($alias)) {
            return;
        }
        $query->addState($alias);

        $versionJoin = '';
        /** @var string|EntityDefinition $definition */
        if ($definition::isVersionAware() && $field->is(CascadeDelete::class)) {
            $versionJoin = ' AND #root#.version_id = #alias#.version_id';
        }

        $catalogJoinCondition = '';
        if ($definition::isCatalogAware() && $reference::isCatalogAware()) {
            $catalogJoinCondition = ' AND #root#.catalog_id = #alias#.catalog_id';
        }

        $query->leftJoin(
            self::escape($root),
            self::escape($table),
            self::escape($alias),
            str_replace(
                ['#root#', '#source_column#', '#alias#', '#reference_column#'],
                [
                    self::escape($root),
                    self::escape($field->getLocalField()),
                    self::escape($alias),
                    self::escape($field->getReferenceField()),
                ],
                '#root#.#source_column# = #alias#.#reference_column#' . $versionJoin . $catalogJoinCondition
            )
        );

        if ($definition === $reference) {
            return;
        }

        if (!$reference::getParentPropertyName()) {
            return;
        }

        $parent = $reference::getFields()->get($reference::getParentPropertyName());
        self::joinManyToOne($reference, $alias, $parent, $query, $context);
    }

    public static function joinManyToMany(string $definition, string $root, ManyToManyAssociationField $field, QueryBuilder $query, ShopContext $context): void
    {
        /** @var EntityDefinition $mapping */
        $mapping = $field->getMappingDefinition();
        $table = $mapping::getEntityName();

        $mappingAlias = $root . '.' . $field->getPropertyName() . '.mapping';

        if ($query->hasState($mappingAlias)) {
            return;
        }
        $query->addState($mappingAlias);

        $versionJoinCondition = '';
        /** @var string|EntityDefinition $definition */
        if ($definition::isVersionAware() && $mapping::isVersionAware() && $field->is(CascadeDelete::class)) {
            $versionField = $definition::getEntityName() . '_version_id';
            $versionJoinCondition = ' AND #root#.version_id = #alias#.' . $versionField;
        }

        $query->leftJoin(
            self::escape($root),
            self::escape($table),
            self::escape($mappingAlias),
            str_replace(
                ['#root#', '#source_column#', '#alias#', '#reference_column#'],
                [
                    self::escape($root),
                    self::escape('id'),
                    self::escape($mappingAlias),
                    self::escape($field->getMappingLocalColumn()),
                ],
                '#root#.#source_column# = #alias#.#reference_column#' . $versionJoinCondition
            )
        );

        /** @var EntityDefinition|string $reference */
        $reference = $field->getReferenceDefinition();
        $table = $reference::getEntityName();

        $alias = $root . '.' . $field->getPropertyName();

        $versionJoinCondition = '';
        /* @var string|EntityDefinition $definition */
        if ($reference::isVersionAware()) {
            $versionField = $reference::getEntityName() . '_version_id';
            $versionJoinCondition = 'AND #alias#.version_id = #mapping#.' . $versionField;
        }

        $catalogJoinCondition = '';
        if ($definition::isCatalogAware() && $reference::isCatalogAware()) {
            $catalogJoinCondition = ' AND #root#.catalog_id = #alias#.catalog_id';
        }

        $query->leftJoin(
            self::escape($mappingAlias),
            self::escape($table),
            self::escape($alias),
            str_replace(
                ['#mapping#', '#source_column#', '#alias#', '#reference_column#', '#root#'],
                [
                    self::escape($mappingAlias),
                    self::escape($field->getMappingReferenceColumn()),
                    self::escape($alias),
                    self::escape($field->getReferenceField()),
                    self::escape($root),
                ],
                '#mapping#.#source_column# = #alias#.#reference_column# ' . $versionJoinCondition . $catalogJoinCondition
            )
        );

        if ($definition === $reference) {
            return;
        }

        if (!$reference::getParentPropertyName()) {
            return;
        }

        $parent = $reference::getFields()->get($reference::getParentPropertyName());
        self::joinManyToOne($reference, $alias, $parent, $query, $context);
    }

    public static function joinTranslation(string $root, string $definition, QueryBuilder $query, ShopContext $context, bool $raw = false): void
    {
        self::joinTranslationTable($root, $definition, $query, $context);

        if (!$definition::getParentPropertyName() || $raw) {
            return;
        }

        /** @var EntityDefinition $definition */
        $parent = $definition::getFields()->get($definition::getParentPropertyName());
        $alias = $root . '.' . $parent->getPropertyName();

        self::joinTranslationTable($alias, $definition, $query, $context);
    }

    public static function addTranslationSelect(string $root, string $definition, QueryBuilder $query, ShopContext $context, array $fields, bool $raw = false): void
    {
        self::joinTranslation($root, $definition, $query, $context, $raw);

        [$chain, $inheritedChain] = self::buildTranslationChain($root, $definition, $context, $raw);

        /** @var TranslatedField $field */
        foreach ($fields as $property => $field) {
            $query->addSelect(
                self::getTranslationFieldAccessor($root, $field, $chain, $inheritedChain)
                . ' as ' .
                self::escape($root . '.' . $field->getPropertyName())
            );
        }
    }

    public static function uuidStringsToBytes(array $ids)
    {
        return array_map(function (string $id) {
            return Uuid::fromStringToBytes($id);
        }, $ids);
    }

    private static function joinVersion(QueryBuilder $query, string $definition, string $root, ShopContext $context): void
    {
        /** @var string|EntityDefinition $definition */
        $table = $definition::getEntityName();

        $connection = $query->getConnection();
        $versionQuery = $connection->createQueryBuilder();
        $versionQuery->select([
            'COALESCE(draft.id, live.id) as id',
            'COALESCE(draft.version_id, live.version_id) as version_id',
        ]);
        $versionQuery->from(self::escape($table), 'live');
        $versionQuery->leftJoin('live', self::escape($table), 'draft', 'draft.id = live.id AND draft.version_id = :version');
        $versionQuery->andWhere('live.version_id = :liveVersion');

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
                '#version#.version_id = #root#.version_id AND #version#.id = #root#.id'
            )
        );
    }

    private static function joinTranslationTable(string $root, string $definition, QueryBuilder $query, ShopContext $context): void
    {
        $alias = $root . '.translation';
        if ($query->hasState($alias)) {
            return;
        }

        $query->addState($alias);

        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName() . '_translation';

        $languageId = Uuid::fromStringToBytes($context->getLanguageId());
        $query->setParameter('languageId', $languageId);

        $versionJoin = '';
        if ($definition::isVersionAware()) {
            $versionJoin = ' AND #alias#.version_id = #root#.version_id';
        }

        $query->leftJoin(
            self::escape($root),
            self::escape($table),
            self::escape($alias),
            str_replace(
                ['#alias#', '#entity#', '#root#'],
                [
                    self::escape($alias),
                    $definition::getEntityName(),
                    self::escape($root),
                ],
                '#alias#.#entity#_id = #root#.id AND #alias#.language_id = :languageId' . $versionJoin
            )
        );

        if (!$context->hasFallback()) {
            return;
        }

        $alias = $root . '.translation.fallback';

        $query->leftJoin(
            self::escape($root),
            self::escape($table),
            self::escape($alias),
            str_replace(
                ['#alias#', '#entity#', '#root#'],
                [
                    self::escape($alias),
                    $definition::getEntityName(),
                    self::escape($root),
                ],
                '#alias#.#entity#_id = #root#.id AND #alias#.language_id = :fallbackLanguageId' . $versionJoin
            )
        );
        $languageId = Uuid::fromStringToBytes($context->getFallbackLanguageId());
        $query->setParameter('fallbackLanguageId', $languageId);
    }

    private static function getTranslationFieldAccessor(string $root, TranslatedField $field, array $chain, array $inheritedChain = []): string
    {
        $alias = $root . '.translation';
        if (count($inheritedChain) === 1) {
            return self::escape($alias) . '.' . self::escape($field->getStorageName());
        }

        $fieldChain = $chain;
        if ($field->is(Inherited::class)) {
            $fieldChain = $inheritedChain;
        }

        $chainSelect = [];
        foreach ($fieldChain as $table) {
            $chainSelect[] = self::escape($table) . '.' . self::escape($field->getStorageName());
        }

        return sprintf('COALESCE(%s)', implode(',', $chainSelect));
    }

    private static function buildTranslationChain(string $root, string $definition, ShopContext $context, bool $raw = false): array
    {
        $chain = [$root . '.translation'];
        $inheritedChain = [$root . '.translation'];

        /** @var string|EntityDefinition $definition */
        if ($definition::getParentPropertyName() && !$raw) {
            /** @var EntityDefinition|string $definition */
            $parentName = $definition::getParentPropertyName();
            $inheritedChain[] = $root . '.' . $parentName . '.translation';
        }

        if ($context->hasFallback()) {
            $inheritedChain[] = $root . '.translation.fallback';
            $chain[] = $root . '.translation.fallback';
        }

        if ($definition::getParentPropertyName() && $context->hasFallback() && !$raw) {
            /** @var EntityDefinition|string $definition */
            $parentName = $definition::getParentPropertyName();
            $inheritedChain[] = $root . '.' . $parentName . '.translation.fallback';
        }

        return [$chain, $inheritedChain];
    }
}
