<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 */
#[Package('content')]
class UnusedMediaPurger
{
    private const VALID_ASSOCIATIONS = [
        ManyToManyAssociationField::class,
        OneToManyAssociationField::class,
        OneToOneAssociationField::class,
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $mediaRepo,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @internal This method is used only by the media:delete-unused command and is subject to change
     */
    public function getNotUsedMedia(?int $limit = 50, ?int $offset = null, ?int $gracePeriodDays = null, ?string $folderEntity = null): \Generator
    {
        $limit ??= 50;
        $gracePeriodDays ??= 0;

        $context = Context::createDefaultContext();

        $criteria = $this->createFilterForNotUsedMedia($folderEntity);
        $criteria->addSorting(new FieldSorting('media.createdAt', FieldSorting::ASCENDING));
        $criteria->setLimit($limit);

        //if we provided an offset, then just grab that batch based on the limit
        if ($offset !== null) {
            $criteria->setOffset($offset);

            /** @var array<string> $ids */
            $ids = $this->mediaRepo->searchIds($criteria, $context)->getIds();
            $ids = $this->filterOutNewMedia($ids, $gracePeriodDays);
            $ids = $this->dispatchEvent($ids);

            return yield array_values($this->mediaRepo->search(new Criteria($ids), $context)->getElements());
        }

        //otherwise, we need iterate over the entire result set in batches
        $iterator = new RepositoryIterator($this->mediaRepo, $context, $criteria);
        while (($ids = $iterator->fetchIds()) !== null) {
            $ids = $this->filterOutNewMedia($ids, $gracePeriodDays);
            $unusedIds = $this->dispatchEvent($ids);

            if (empty($unusedIds)) {
                continue;
            }

            yield array_values($this->mediaRepo->search(new Criteria($unusedIds), $context)->getElements());
        }
    }

    public function deleteNotUsedMedia(
        ?int $limit = 50,
        ?int $offset = null,
        ?int $gracePeriodDays = null,
        ?string $folderEntity = null,
    ): int {
        $limit ??= 50;
        $gracePeriodDays ??= 0;
        $deletedTotal = 0;

        foreach ($this->getUnusedMediaIds($limit, $offset, $folderEntity) as $idBatch) {
            $idBatch = $this->filterOutNewMedia($idBatch, $gracePeriodDays);

            $this->mediaRepo->delete(
                array_map(static fn ($id) => ['id' => $id], $idBatch),
                Context::createDefaultContext()
            );

            $deletedTotal += \count($idBatch);
        }

        return $deletedTotal;
    }

    /**
     * @param array<string> $mediaIds
     *
     * @return array<string>
     */
    private function filterOutNewMedia(array $mediaIds, int $gracePeriodDays): array
    {
        if ($gracePeriodDays === 0) {
            return $mediaIds;
        }

        $threeDaysAgo = (new \DateTime())->sub(new \DateInterval(sprintf('P%dD', $gracePeriodDays)));
        $rangeFilter = new RangeFilter('uploadedAt', ['lt' => $threeDaysAgo->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $criteria = new Criteria($mediaIds);
        $criteria->addFilter($rangeFilter);

        /** @var array<string> $ids */
        $ids = $this->mediaRepo->searchIds($criteria, Context::createDefaultContext())->getIds();

        return $ids;
    }

    /**
     * @return \Generator<int, array<string>>
     */
    private function getUnusedMediaIds(int $limit, ?int $offset = null, ?string $folderEntity = null): \Generator
    {
        $context = Context::createDefaultContext();

        $criteria = $this->createFilterForNotUsedMedia($folderEntity);
        $criteria->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));
        $criteria->setLimit($limit);
        $criteria->setOffset(0);

        //if we provided an offset, then just grab that batch based on the limit
        if ($offset !== null) {
            $criteria->setOffset($offset);

            /** @var array<string> $ids */
            $ids = $this->mediaRepo->searchIds($criteria, $context)->getIds();

            return yield $this->dispatchEvent($ids);
        }

        //in order to iterate all records whilst deleting them, we must adjust the offset for each batch
        //using the amount of deleted records in the previous batch
        //eg: we start from offset 0. we search for 50, and delete 3 of them. Now we start from offset 47.
        while (!empty($ids = $this->mediaRepo->searchIds($criteria, $context)->getIds())) {
            /** @var array<string> $ids */
            $unusedIds = $this->dispatchEvent($ids);

            if (!empty($unusedIds)) {
                yield $unusedIds;
            }

            $criteria->setOffset(($criteria->getOffset() + $limit) - \count($unusedIds));
        }
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function dispatchEvent(array $ids): array
    {
        $event = new UnusedMediaSearchEvent(array_values($ids));
        $this->eventDispatcher->dispatch($event);

        return $event->getUnusedIds();
    }

    /**
     * Here we attempt to exclude entity associations that are extending the behaviour of the media entity rather than
     * referencing media. For example, `MediaThumbnailDefinition` adds thumbnail support, whereas `ProductMediaDefinition`
     * adds support for images to products.
     */
    private function isInsideTopLevelDomain(string $domain, EntityDefinition $definition): bool
    {
        if ($definition->getParentDefinition() === null) {
            return false;
        }

        if ($definition->getParentDefinition()->getEntityName() === $domain) {
            return true;
        }

        return $this->isInsideTopLevelDomain($domain, $definition->getParentDefinition());
    }

    private function createFilterForNotUsedMedia(?string $folderEntity = null): Criteria
    {
        $criteria = new Criteria();

        foreach ($this->mediaRepo->getDefinition()->getFields() as $field) {
            if (!$field instanceof AssociationField) {
                continue;
            }

            if (!\in_array($field::class, self::VALID_ASSOCIATIONS, true)) {
                continue;
            }

            $definition = $field->getReferenceDefinition();

            if ($field instanceof ManyToManyAssociationField) {
                $definition = $field->getToManyReferenceDefinition();
            }

            if ($this->isInsideTopLevelDomain(MediaDefinition::ENTITY_NAME, $definition)) {
                continue;
            }

            $fkey = $definition->getFields()->getByStorageName($field->getReferenceField());

            if ($fkey === null) {
                continue;
            }

            $criteria->addFilter(
                new EqualsFilter(sprintf('media.%s.%s', $field->getPropertyName(), $fkey->getPropertyName()), null)
            );
        }

        if ($folderEntity) {
            $criteria->addFilter(
                new EqualsAnyFilter('media.mediaFolder.defaultFolder.entity', [strtolower($folderEntity)])
            );
        }

        return $criteria;
    }
}
