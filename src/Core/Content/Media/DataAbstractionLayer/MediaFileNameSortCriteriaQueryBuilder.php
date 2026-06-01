<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\CriteriaPartResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinGroupBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Log\Package;

/**
 * Keeps the media filename sort-key column as a DBAL-only implementation detail.
 *
 * Sorting large media folders by `fileName` is slow because the public `file_name` storage is intentionally a long text
 * field. Limiting the public filename length would be a breaking change, so the migration adds a generated, bounded
 * `file_name_sort_key` column that can be indexed for the common folder listing query. The DAL contract should still
 * expose only the existing `fileName` field though: API callers, extensions, Elasticsearch and entity hydration should
 * not learn about the generated helper column.
 *
 * This decorator therefore intercepts only the DBAL SQL order generation for the media definition. Criteria continue to
 * contain `fileName`, while MySQL/MariaDB receive `ORDER BY media.file_name_sort_key`. Elasticsearch searches do not use
 * this DBAL query builder, so they keep their own mapping for the original public field.
 *
 * @internal
 */
#[Package('discovery')]
class MediaFileNameSortCriteriaQueryBuilder extends CriteriaQueryBuilder
{
    private const FILE_NAME_FIELD = 'fileName';

    private const FILE_NAME_SORT_KEY_COLUMN = 'file_name_sort_key';

    public function __construct(
        SqlQueryParser $parser,
        EntityDefinitionQueryHelper $helper,
        SearchTermInterpreter $interpreter,
        EntityScoreQueryBuilder $scoreBuilder,
        JoinGroupBuilder $joinGrouper,
        CriteriaPartResolver $criteriaPartResolver
    ) {
        parent::__construct($parser, $helper, $interpreter, $scoreBuilder, $joinGrouper, $criteriaPartResolver);
    }

    /**
     * @param array<FieldSorting> $sortings
     */
    public function addSortings(EntityDefinition $definition, Criteria $criteria, array $sortings, QueryBuilder $query, Context $context): void
    {
        if ($definition->getEntityName() !== MediaDefinition::ENTITY_NAME) {
            parent::addSortings($definition, $criteria, $sortings, $query, $context);

            return;
        }

        foreach ($sortings as $sorting) {
            if (!$this->isFileNameSorting($sorting)) {
                parent::addSortings($definition, $criteria, [$sorting], $query, $context);

                continue;
            }

            $this->addFileNameSortKeySorting($definition, $criteria, $sorting, $query, $context);
        }
    }

    private function isFileNameSorting(FieldSorting $sorting): bool
    {
        if ($sorting instanceof CountSorting) {
            return false;
        }

        return \in_array($sorting->getField(), [
            self::FILE_NAME_FIELD,
            MediaDefinition::ENTITY_NAME . '.' . self::FILE_NAME_FIELD,
        ], true);
    }

    private function addFileNameSortKeySorting(EntityDefinition $definition, Criteria $criteria, FieldSorting $sorting, QueryBuilder $query, Context $context): void
    {
        if (!$this->isValidSortingDirection($sorting->getDirection())) {
            parent::addSortings($definition, $criteria, [$sorting], $query, $context);

            return;
        }

        $accessor = $this->getFileNameSortKeyAccessor();

        if ($sorting->getNaturalSorting()) {
            $query->addOrderBy('LENGTH(' . $accessor . ')', $sorting->getDirection());
        }

        if (!$this->hasGroupBy($criteria, $query)) {
            $query->addOrderBy($accessor, $sorting->getDirection());

            return;
        }

        if ($sorting->getDirection() === FieldSorting::ASCENDING) {
            $accessor = 'MIN(' . $accessor . ')';
        } else {
            $accessor = 'MAX(' . $accessor . ')';
        }

        $query->addOrderBy($accessor, $sorting->getDirection());
    }

    private function getFileNameSortKeyAccessor(): string
    {
        return EntityDefinitionQueryHelper::escape(MediaDefinition::ENTITY_NAME) . '.' . EntityDefinitionQueryHelper::escape(self::FILE_NAME_SORT_KEY_COLUMN);
    }

    private function hasGroupBy(Criteria $criteria, QueryBuilder $query): bool
    {
        if ($query->hasState(EntityReader::TO_MANY_ASSOCIATION_LIMIT_QUERY)) {
            return false;
        }

        return $query->hasState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN) || $criteria->getGroupFields() !== [];
    }

    private function isValidSortingDirection(string $direction): bool
    {
        return \in_array(mb_strtoupper($direction), [FieldSorting::ASCENDING, FieldSorting::DESCENDING], true);
    }
}
