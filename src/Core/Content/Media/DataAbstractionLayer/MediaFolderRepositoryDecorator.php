<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class MediaFolderRepositoryDecorator implements EntityRepositoryInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $innerRepo;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    public function __construct(
        EntityRepositoryInterface $innerRepo,
        EntityRepositoryInterface $mediaRepository
    ) {
        $this->innerRepo = $innerRepo;
        $this->mediaRepository = $mediaRepository;
    }

    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        $criteria = new Criteria($this->getRawIds($ids));
        $criteria->addAssociation('children');
        $affectedFolders = $this->search($criteria, $context);

        /** @var MediaFolderCollection $folders */
        $folders = $affectedFolders->getEntities();
        $this->deleteMediaAndSubfolders($folders, $context);

        return $this->innerRepo->delete($ids, $context);
    }

    // Unchanged methods
    public function aggregate(Criteria $criteria, Context $context): AggregationResultCollection
    {
        return $this->innerRepo->aggregate($criteria, $context);
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        if ($context->getScope() !== Context::SYSTEM_SCOPE) {
            $criteria->addFilter(
                new MultiFilter('OR', [
                    new EqualsFilter('media_folder.configuration.private', false),
                    new EqualsFilter('media_folder.configuration.private', null),
                ])
            );
        }

        return $this->innerRepo->searchIds($criteria, $context);
    }

    public function clone(string $id, Context $context, ?string $newId = null): EntityWrittenContainerEvent
    {
        return $this->innerRepo->clone($id, $context, $newId);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        if ($context->getScope() !== Context::SYSTEM_SCOPE) {
            $criteria->addFilter(
                new MultiFilter('OR', [
                    new EqualsFilter('media_folder.configuration.private', false),
                    new EqualsFilter('media_folder.configuration.private', null),
                ])
            );
        }

        return $this->innerRepo->search($criteria, $context);
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->innerRepo->update($data, $context);
    }

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->innerRepo->upsert($data, $context);
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->innerRepo->create($data, $context);
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->innerRepo->createVersion($id, $context, $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        $this->innerRepo->merge($versionId, $context);
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->innerRepo->getDefinition();
    }

    private function getRawIds(array $ids)
    {
        return array_column($ids, 'id');
    }

    private function deleteMediaAndSubfolders(MediaFolderCollection $folders, Context $context): void
    {
        foreach ($folders as $folder) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('mediaFolderId', $folder->getId()));

            $mediaResult = $this->mediaRepository->searchIds($criteria, $context);

            if ($mediaResult->getTotal() > 0) {
                $affectedMediaIds = array_map(function (string $id) {
                    return ['id' => $id];
                }, $mediaResult->getIds());

                $this->mediaRepository->delete($affectedMediaIds, $context);
            }

            if ($folder->getChildren() === null) {
                $this->loadChildFolders($folder, $context);
            }

            $this->deleteMediaAndSubfolders($folder->getChildren(), $context);
        }
    }

    private function loadChildFolders(MediaFolderEntity $folder, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $folder->getId()));
        /** @var MediaFolderCollection $childFolders */
        $childFolders = $this->search($criteria, $context)->getEntities();
        $folder->setChildren($childFolders);
    }
}
