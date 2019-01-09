<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Exception\MediaFolderNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\Uuid;

class MediaFolderService
{
    /**
     * @var RepositoryInterface
     */
    private $mediaRepo;

    /**
     * @var RepositoryInterface
     */
    private $mediaFolderRepo;

    /**
     * @var RepositoryInterface
     */
    private $mediaFolderConfigRepo;

    public function __construct(
        RepositoryInterface $mediaRepo,
        RepositoryInterface $mediaFolderRepo,
        RepositoryInterface $mediaFolderConfigRepo
    ) {
        $this->mediaRepo = $mediaRepo;
        $this->mediaFolderRepo = $mediaFolderRepo;
        $this->mediaFolderConfigRepo = $mediaFolderConfigRepo;
    }

    public function dissolve(string $folderId, Context $context): void
    {
        $folder = $this->fetchFolder($folderId, $context);

        $this->moveMediaToParentFolder($folder, $context);
        $this->moveSubFoldersToParent($folder, $context);
        $this->mediaFolderRepo->delete([['id' => $folder->getId()]], $context);
    }

    public function move(string $folderToMoveId, ?string $targetFolderId, Context $context): void
    {
        $folderToMove = $this->fetchFolder($folderToMoveId, $context);

        if ($targetFolderId) {
            // ensure Folder exists
            $this->fetchFolder($targetFolderId, $context);
        }

        if (!$folderToMove->getUseParentConfiguration()) {
            $this->mediaFolderRepo->update([
                [
                    'id' => $folderToMoveId,
                    'parentId' => $targetFolderId,
                ],
            ], $context);

            return;
        }

        $newConfigId = $this->cloneConfiguration($folderToMove->getConfigurationId(), $context);

        $updates = [
            [
                'id' => $folderToMoveId,
                'parentId' => $targetFolderId,
                'useParentConfiguration' => false,
                'configurationId' => $newConfigId,
            ],
        ];
        $updates = array_merge($updates, $this->updateSubFolder($folderToMoveId, $newConfigId, $context));

        $this->mediaFolderRepo->update($updates, $context);
    }

    private function moveMediaToParentFolder(MediaFolderEntity $folder, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mediaFolderId', $folder->getId()));
        $mediaIds = $this->mediaRepo->searchIds($criteria, $context)->getIds();

        $payload = [];
        foreach ($mediaIds as $mediaId) {
            $payload[] = [
                'id' => $mediaId,
                'mediaFolderId' => $folder->getParentId(),
            ];
        }

        if (count($payload) > 0) {
            $this->mediaRepo->update($payload, $context);
        }
    }

    private function moveSubFoldersToParent(MediaFolderEntity $folder, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $folder->getId()));
        $subFolders = $this->mediaFolderRepo->search($criteria, $context);

        if ($subFolders->getTotal() === 0) {
            $this->deleteOwnConfiguration($folder, $context);

            return;
        }

        $payload = [];
        foreach ($subFolders->getEntities() as $subFolder) {
            $payload[] = [
                'id' => $subFolder->getId(),
                'parentId' => $folder->getParentId(),
            ];
        }

        $this->mediaFolderRepo->update($payload, $context);

        $subFolders = $subFolders->filterByProperty('useParentConfiguration', true);

        if (count($subFolders) === 0) {
            $this->deleteOwnConfiguration($folder, $context);
        }

        if ((!$folder->getUseParentConfiguration()) && count($subFolders) > 1) {
            $this->duplicateFolderConfig($subFolders->getEntities(), $context);
        }
    }

    private function duplicateFolderConfig(MediaFolderCollection $subFolders, Context $context): void
    {
        $subFolders = $subFolders->getElements();
        /** @var MediaFolderEntity $folder */
        $folder = array_shift($subFolders);
        $config = $folder->getConfiguration();

        $payload = [
            [
                'id' => $folder->getId(),
                'useParentConfiguration' => false,
            ],
        ];
        foreach ($subFolders as $folder) {
            $configurationId = $this->cloneConfiguration($config->getId(), $context);

            $payload[] = [
                'id' => $folder->getId(),
                'useParentConfiguration' => false,
                'configurationId' => $configurationId,
            ];

            $payload = array_merge($payload, $this->updateSubFolder($folder->getId(), $configurationId, $context));
        }

        if (count($payload) > 0) {
            $this->mediaFolderRepo->update($payload, $context);
        }
    }

    private function updateSubFolder(
        string $parentId,
        string $configurationId,
        Context $context
    ): array {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $parentId));
        $criteria->addFilter(new EqualsFilter('useParentConfiguration', true));
        /** @var MediaFolderCollection $subFolders */
        $subFolders = $this->mediaFolderRepo->search($criteria, $context)->getEntities();
        $payload = [];

        foreach ($subFolders as $subFolder) {
            $payload[] = [
                'id' => $subFolder->getId(),
                'configurationId' => $configurationId,
            ];

            $payload = array_merge($payload, $this->updateSubFolder($subFolder->getId(), $configurationId, $context));
        }

        return $payload;
    }

    private function deleteOwnConfiguration(MediaFolderEntity $folder, Context $context): void
    {
        if ($folder->getUseParentConfiguration() === false) {
            $this->mediaFolderConfigRepo->delete([['id' => $folder->getConfigurationId()]], $context);
        }
    }

    private function cloneConfiguration(string $configId, Context $context): string
    {
        $newId = Uuid::uuid4()->getHex();
        $this->mediaFolderConfigRepo->clone($configId, $context, $newId);

        return $newId;
    }

    private function fetchFolder(string $folderId, Context $context)
    {
        $folder = $this->mediaFolderRepo->read(new ReadCriteria([$folderId]), $context)->get($folderId);

        if ($folder === null) {
            throw new MediaFolderNotFoundException($folderId);
        }

        return $folder;
    }
}
