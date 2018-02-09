<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\AssociationInterface;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\FieldAware\SqlParseAware;
use Shopware\Api\Entity\Write\FieldAware\StorageAware;
use Shopware\Api\Entity\Write\Flag\Inherited;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Defaults;

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

    public static function getFieldAccessor(string $fieldName, string $definition, string $root, TranslationContext $context): string
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

            if ($field instanceof SqlParseAware) {
                return $field->parse($root, $context);
            }

            if ($field instanceof TranslatedField) {
                [$chain, $inheritedChain] = self::buildTranslationChain($root, $definition, $context);

                return self::getTranslationFieldAccessor($root, $field, $chain, $inheritedChain);
            }

            if ($field instanceof StorageAware) {
                $select = implode('.', [
                    self::escape($root),
                    self::escape($field->getStorageName()),
                ]);

                if (!$field->is(Inherited::class)) {
                    return $select;
                }

                $parentSelect = implode('.', [
                    self::escape($root . '.' . $definition::getParentPropertyName()),
                    self::escape($field->getStorageName()),
                ]);

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

    public static function getBaseQuery(Connection $connection, string $definition, TranslationContext $context): QueryBuilder
    {
        /** @var string|EntityDefinition $definition */
        $table = $definition::getEntityName();

        $query = new QueryBuilder($connection);
        $query->from(self::escape($table), self::escape($table));

        if ($definition::isVersionAware() && $context->getVersionId() !== Defaults::LIVE_VERSION) {
            self::buildVersionFrom($connection, $query, $definition, $context);

        } else if ($definition::isVersionAware()) {
            $query->andWhere(self::escape($table) . '.`version_id` = :version');
            $query->setParameter('version', Uuid::fromString($context->getVersionId())->getBytes());
        }

        return $query;
    }

    public static function joinField(string $fieldName, string $definition, string $root, QueryBuilder $query, TranslationContext $context): void
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
            self::joinManyToOne($definition, $root, $field, $query);
            $referenceClass = $field->getReferenceClass();
        }

        if ($field instanceof OneToManyAssociationField) {
            self::joinOneToMany($definition, $root, $field, $query);
            $query->addState(self::REQUIRES_GROUP_BY);
            $referenceClass = $field->getReferenceClass();
        }

        if ($field instanceof ManyToManyAssociationField) {
            self::joinManyToMany($definition, $root, $field, $query);
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
    public static function joinManyToOne(string $definition, string $root, ManyToOneAssociationField $field, QueryBuilder $query): void
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
        if ($definition::isVersionAware() && $reference::isVersionAware()) {
            $versionJoin = ' AND #root#.version_id = #alias#.version_id';
        }

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
                '#root#.#source_column# = #alias#.#reference_column#' . $versionJoin
            )
        );

        if ($definition === $reference) {
            return;
        }

        if (!$reference::getParentPropertyName()) {
            return;
        }

        $parent = $reference::getFields()->get($reference::getParentPropertyName());
        self::joinManyToOne($reference, $alias, $parent, $query);
    }

    public static function joinOneToMany(string $definition, string $root, OneToManyAssociationField $field, QueryBuilder $query): void
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
        if ($definition::isVersionAware()) {
            $versionJoin = ' AND #root#.version_id = #alias#.version_id';
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
                    self::escape($field->getReferenceField())
                ],
                '#root#.#source_column# = #alias#.#reference_column#' . $versionJoin
            )
        );

        if ($definition === $reference) {
            return;
        }

        if (!$reference::getParentPropertyName()) {
            return;
        }

        $parent = $reference::getFields()->get($reference::getParentPropertyName());
        self::joinManyToOne($reference, $alias, $parent, $query);
    }

    public static function joinManyToMany(string $definition, string $root, ManyToManyAssociationField $field, QueryBuilder $query): void
    {
        /** @var EntityDefinition $mapping */
        $mapping = $field->getMappingDefinition();
        $table = $mapping::getEntityName();

        $mappingAlias = $root . '.' . $field->getPropertyName() . '.mapping';

        if ($query->hasState($mappingAlias)) {
            return;
        }
        $query->addState($mappingAlias);

        $versionJoin = '';
        /** @var string|EntityDefinition $definition */
        if ($definition::isVersionAware() && $mapping::isVersionAware()) {
            $versionJoin = ' AND #root#.version_id = #alias#.version_id';
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
                '#root#.#source_column# = #alias#.#reference_column#' . $versionJoin
            )
        );

        /** @var EntityDefinition|string $reference */
        $reference = $field->getReferenceDefinition();
        $table = $reference::getEntityName();

        $alias = $root . '.' . $field->getPropertyName();

        $versionJoin = '';
        /** @var string|EntityDefinition $definition */
        if ($reference::isVersionAware()) {
            $versionJoin = 'AND #alias#.version_id = :liveVersion';
            $query->setParameter('liveVersion', Uuid::fromString(Defaults::LIVE_VERSION)->getBytes());
        }

        $query->leftJoin(
            self::escape($mappingAlias),
            self::escape($table),
            self::escape($alias),
            str_replace(
                ['#mapping#', '#source_column#', '#alias#', '#reference_column#'],
                [
                    self::escape($mappingAlias),
                    self::escape($field->getMappingReferenceColumn()),
                    self::escape($alias),
                    self::escape($field->getReferenceField())
                ],
                '#mapping#.#source_column# = #alias#.#reference_column# ' . $versionJoin
            )
        );

        if ($definition === $reference) {
            return;
        }

        if (!$reference::getParentPropertyName()) {
            return;
        }

        $parent = $reference::getFields()->get($reference::getParentPropertyName());
        self::joinManyToOne($reference, $alias, $parent, $query);
    }

    public static function joinTranslation(string $root, string $definition, QueryBuilder $query, TranslationContext $context): void
    {
        self::joinTranslationTable($root, $definition, $query, $context);

        if (!$definition::getParentPropertyName()) {
            return;
        }

        /** @var EntityDefinition $definition */
        $parent = $definition::getFields()->get($definition::getParentPropertyName());
        $alias = $root . '.' . $parent->getPropertyName();

        self::joinTranslationTable($alias, $definition, $query, $context);
    }

    public static function addTranslationSelect(string $root, string $definition, QueryBuilder $query, TranslationContext $context, FieldCollection $fields): void
    {
        self::joinTranslation($root, $definition, $query, $context);

        [$chain, $inheritedChain] = self::buildTranslationChain($root, $definition, $context);

        /** @var TranslatedField $field */
        foreach ($fields->getElements() as $property => $field) {
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
            return Uuid::fromString($id)->getBytes();
        }, $ids);
    }

    private static function buildVersionFrom(Connection $connection, QueryBuilder $query, string $definition, TranslationContext $context): void
    {
        /** @var string|EntityDefinition $definition */
        $root = $definition::getEntityName();

        $versionQuery = $connection->createQueryBuilder();
        $versionQuery->select([
            'coalesce(draft.id, live.id) as id',
            'coalesce(draft.version_id, live.version_id) as version_id'
        ]);
        $versionQuery->from(self::escape($root), 'live');
        $versionQuery->leftJoin('live', self::escape($root), 'draft', 'draft.id = live.id AND draft.version_id = :version');
        $versionQuery->andWhere('live.version_id = :liveVersion');

        $query->setParameter('liveVersion', Uuid::fromString(Defaults::LIVE_VERSION)->getBytes());
        $query->setParameter('version', Uuid::fromString($context->getVersionId())->getBytes());

        $versionRoot = $root . '_version';

        $query->innerJoin(
            self::escape($root),
            '(' . $versionQuery->getSQL() . ')',
            self::escape($versionRoot),
            str_replace(
                ['#version#', '#root#'],
                [
                    self::escape($versionRoot),
                    self::escape($root)
                ],
                '#version#.version_id = #root#.version_id AND #version#.id = #root#.id'
            )
        );
    }

    private static function joinTranslationTable(string $root, string $definition, QueryBuilder $query, TranslationContext $context): void
    {
        $alias = $root . '.translation';
        if ($query->hasState($alias)) {
            return;
        }

        $query->addState($alias);

        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName() . '_translation';

        $languageId = Uuid::fromString($context->getShopId())->getBytes();
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
                    self::escape($root)
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
                    self::escape($root)
                ],
                '#alias#.#entity#_id = #root#.id AND #alias#.language_id = :fallbackLanguageId' . $versionJoin
            )
        );
        $languageId = Uuid::fromString($context->getFallbackId())->getBytes();
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

    private static function buildTranslationChain(string $root, string $definition, TranslationContext $context): array
    {
        $chain = [$root . '.translation'];
        $inheritedChain = [$root . '.translation'];

        /** @var string|EntityDefinition $definition */
        if ($definition::getParentPropertyName()) {
            /** @var EntityDefinition|string $definition */
            $parentName = $definition::getParentPropertyName();
            $inheritedChain[] = $root . '.' . $parentName . '.translation';
        }

        if ($context->hasFallback()) {
            $inheritedChain[] = $root . '.translation.fallback';
            $chain[] = $root . '.translation.fallback';
        }

        if ($definition::getParentPropertyName() && $context->hasFallback()) {
            /** @var EntityDefinition|string $definition */
            $parentName = $definition::getParentPropertyName();
            $inheritedChain[] = $root . '.' . $parentName . '.translation.fallback';
        }

        return [$chain, $inheritedChain];
    }
}
