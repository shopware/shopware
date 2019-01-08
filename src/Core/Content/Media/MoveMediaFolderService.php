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

class MoveMediaFolderService
{
    /**
     * @var RepositoryInterface
     */
    private $mediaFolderRepo;

    /**
     * @var RepositoryInterface
     */
    private $mediaFolderConfigRepo;

    public function __construct(RepositoryInterface $mediaFolderRepo, RepositoryInterface $mediaFolderConfigRepo)
    {
        $this->mediaFolderRepo = $mediaFolderRepo;
        $this->mediaFolderConfigRepo = $mediaFolderConfigRepo;
    }

    public function move(string $folderToMoveId, string $targetFolderId, Context $context): void
    {
        $folders = $this->fetchFoldersFromRepo([$folderToMoveId, $targetFolderId], $context);
        $folderToMove = $folders->get($folderToMoveId);

        if (!$folderToMove->isUseParentConfiguration()) {
            $this->mediaFolderRepo->update([
                [
                    'id' => $folderToMoveId,
                    'parentId' => $targetFolderId,
                ],
            ], $context);

            return;
        }
        $newConfigId = $this->cloneConfiguration($folderToMove, $context);

        $updates = [
            [
                'id' => $folderToMoveId,
                'parentId' => $targetFolderId,
                'useParentConfiguration' => false,
                'configurationId' => $newConfigId,
            ],
        ];
        $updates = array_merge($updates, $this->updateSubFolder($folderToMoveId, $newConfigId, [], $context));

        $this->mediaFolderRepo->update($updates, $context);
    }

    private function fetchFoldersFromRepo(
        array $ids,
        Context $context
    ): MediaFolderCollection {
        /** @var MediaFolderCollection $folders */
        $folders = $this->mediaFolderRepo->read(new ReadCriteria($ids), $context);

        if ($folders->count() !== 2) {
            $missingFolders = array_diff($ids, $folders->getIds());
            throw new MediaFolderNotFoundException(implode(', ', $missingFolders));
        }

        return $folders;
    }

    private function cloneConfiguration(MediaFolderEntity $folderToMove, Context $context): string
    {
        $newConfigId = Uuid::uuid4()->getHex();
        $this->mediaFolderConfigRepo->clone($folderToMove->getConfigurationId(), $context, $newConfigId);

        return $newConfigId;
    }

    private function updateSubFolder(
        string $parentId,
        string $configurationId,
        array $payload,
        Context $context
    ): array {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $parentId));
        $criteria->addFilter(new EqualsFilter('useParentConfiguration', true));
        /** @var MediaFolderCollection $subFolders */
        $subFolders = $this->mediaFolderRepo->search($criteria, $context)->getEntities();

        foreach ($subFolders as $subFolder) {
            $payload[] = [
                'id' => $subFolder->getId(),
                'configurationId' => $configurationId,
            ];

            $payload = $this->updateSubFolder($subFolder->getId(), $configurationId, $payload, $context);
        }

        return $payload;
    }
}
