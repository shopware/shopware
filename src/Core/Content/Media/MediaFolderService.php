<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Exception\MediaFolderNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('content')]
class MediaFolderService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $mediaRepo,
        private readonly EntityRepository $mediaFolderRepo,
        private readonly EntityRepository $mediaFolderConfigRepo
    ) {
    }

    public function dissolve(string $folderId, Context $context): void
    {
        $folder = $this->fetchFolder($folderId, $context);

        $this->moveMediaToParentFolder($folder, $context);
        $this->moveSubFoldersToParent($folder, $context);
        $this->mediaFolderRepo->delete([['id' => $folder->getId()]], $context);
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

        if (\count($payload) > 0) {
            $this->mediaRepo->update($payload, $context);
        }
    }

    private function moveSubFoldersToParent(MediaFolderEntity $folder, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $folder->getId()));
        $criteria->addAssociation('configuration');
        $subFolders = $this->mediaFolderRepo->search($criteria, $context);

        if ($subFolders->getTotal() === 0) {
            $this->deleteOwnConfiguration($folder, $context);

            return;
        }

        $payload = [];
        foreach ($subFolders->getEntities() as $subFolder) {
            $payload[$subFolder->getId()] = [
                'id' => $subFolder->getId(),
                'parentId' => $folder->getParentId(),
            ];
        }

        $subFolders = $subFolders->filterByProperty('useParentConfiguration', true);

        if (\count($subFolders) === 0) {
            $this->deleteOwnConfiguration($folder, $context);
        }

        if ((!$folder->getUseParentConfiguration()) && \count($subFolders) > 1) {
            /** @var MediaFolderCollection $collection */
            $collection = $subFolders->getEntities();
            $payload = $this->duplicateFolderConfig($collection, $payload, $context);
        }

        $this->mediaFolderRepo->update(array_values($payload), $context);
    }

    private function duplicateFolderConfig(
        MediaFolderCollection $subFolders,
        array $payload,
        Context $context
    ): array {
        $subFolders = $subFolders->getElements();
        /** @var MediaFolderEntity $folder */
        $folder = array_shift($subFolders);
        $config = $folder->getConfiguration();

        $payload[$folder->getId()]['useParentConfiguration'] = false;

        foreach ($subFolders as $folder) {
            $configurationId = $this->cloneConfiguration($config->getId(), $context);

            $payload[$folder->getId()]['useParentConfiguration'] = false;
            $payload[$folder->getId()]['configurationId'] = $configurationId;
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
        $newId = Uuid::randomHex();
        $this->mediaFolderConfigRepo->clone($configId, $context, $newId);

        return $newId;
    }

    private function fetchFolder(string $folderId, Context $context)
    {
        $folder = $this->mediaFolderRepo->search(new Criteria([$folderId]), $context)->get($folderId);

        if ($folder === null) {
            throw new MediaFolderNotFoundException($folderId);
        }

        return $folder;
    }
}
