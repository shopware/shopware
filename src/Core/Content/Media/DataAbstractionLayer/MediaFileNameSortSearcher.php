<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;

/**
 * Rewrites media file name sorting to the indexed generated sort column for DBAL searches only.
 *
 * Large media folders can make `ORDER BY media.file_name` expensive because `file_name` is not bounded tightly enough
 * for a normal full-column sort index, and introducing a stricter public file name length limit would be a breaking
 * change. The media table therefore has a generated, bounded sort key column that can be indexed for the common folder
 * listing query while keeping the public DAL contract on `fileName` unchanged.
 *
 * This decorator intentionally keeps that technical column hidden from callers: criteria still sort by `fileName`, and
 * the rewrite happens only when the request reaches the DBAL searcher. Elasticsearch decorators must see the original
 * criteria first so they can either handle the search themselves or fall back to DBAL, where this optimization applies.
 *
 * @internal
 */
#[Package('discovery')]
class MediaFileNameSortSearcher implements EntitySearcherInterface
{
    private const FILE_NAME_FIELD = 'fileName';

    private const FILE_NAME_SORT_KEY_FIELD = 'fileNameSortKey';

    public function __construct(private readonly EntitySearcherInterface $decorated)
    {
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        if ($definition->getEntityName() !== MediaDefinition::ENTITY_NAME) {
            return $this->decorated->search($definition, $criteria, $context);
        }

        if ($criteria->getSorting() === []) {
            return $this->decorated->search($definition, $criteria, $context);
        }

        $sortings = [];
        $replaced = false;
        foreach ($criteria->getSorting() as $sorting) {
            if ($sorting instanceof CountSorting || !$this->isFileNameSorting($sorting)) {
                $sortings[] = $sorting;

                continue;
            }

            $sortings[] = new FieldSorting(
                $this->getSortKeyField($sorting),
                $sorting->getDirection(),
                $sorting->getNaturalSorting()
            );
            $replaced = true;
        }

        if (!$replaced) {
            return $this->decorated->search($definition, $criteria, $context);
        }

        $criteria = clone $criteria;
        $criteria->resetSorting();
        $criteria->addSorting(...$sortings);

        return $this->decorated->search($definition, $criteria, $context);
    }

    private function isFileNameSorting(FieldSorting $sorting): bool
    {
        return \in_array($sorting->getField(), [
            self::FILE_NAME_FIELD,
            MediaDefinition::ENTITY_NAME . '.' . self::FILE_NAME_FIELD,
        ], true);
    }

    private function getSortKeyField(FieldSorting $sorting): string
    {
        if ($sorting->getField() === MediaDefinition::ENTITY_NAME . '.' . self::FILE_NAME_FIELD) {
            return MediaDefinition::ENTITY_NAME . '.' . self::FILE_NAME_SORT_KEY_FIELD;
        }

        return self::FILE_NAME_SORT_KEY_FIELD;
    }
}
