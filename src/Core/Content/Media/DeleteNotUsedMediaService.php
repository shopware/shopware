<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @deprecated tag:v6.6.0 - Will be removed, use \Shopware\Core\Content\Media\UnusedMediaPurger instead
 */
#[Package('content')]
class DeleteNotUsedMediaService
{
    final public const RESTRICT_DEFAULT_FOLDER_ENTITIES_EXTENSION = 'restrict-default-folder-entities';

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $mediaRepo,
        private readonly EntityRepository $defaultFolderRepo
    ) {
    }

    public function countNotUsedMedia(Context $context): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            sprintf('%s is deprecated and will be removed in v6.6.0.0. Use %s instead.', self::class, UnusedMediaPurger::class),
        );

        $criteria = $this->createFilterForNotUsedMedia($context);
        $criteria->setLimit(1);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        return $this->mediaRepo->search($criteria, $context)->getTotal();
    }

    public function deleteNotUsedMedia(Context $context): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            sprintf('%s is deprecated and will be removed in v6.6.0.0. Use %s instead.', self::class, UnusedMediaPurger::class),
        );

        $criteria = $this->createFilterForNotUsedMedia($context);

        $ids = $this->mediaRepo->searchIds($criteria, $context)->getIds();
        $ids = array_map(static fn ($id) => ['id' => $id], $ids);
        $this->mediaRepo->delete($ids, $context);
    }

    private function createFilterForNotUsedMedia(Context $context): Criteria
    {
        $criteria = new Criteria();

        $defaultFolderCriteria = new Criteria();
        $defaultFolderCriteria->setOffset(0);
        $defaultFolderCriteria->setLimit(50);
        $defaultFolderCriteria->addAssociation('folder.configuration');

        $iterator = new RepositoryIterator($this->defaultFolderRepo, $context, $defaultFolderCriteria);
        while ($defaultFolders = $iterator->fetch()) {
            /** @var MediaDefaultFolderEntity $defaultFolder */
            foreach ($defaultFolders as $defaultFolder) {
                if ($this->isNoAssociation($defaultFolder)) {
                    /** @var MediaFolderEntity $folder */
                    $folder = $defaultFolder->getFolder();

                    $criteria->addFilter(
                        new MultiFilter(
                            'OR',
                            [
                                new NotFilter('AND', [
                                    new EqualsFilter('mediaFolderId', $folder->getId()),
                                ]),
                                new EqualsFilter('mediaFolderId', null),
                            ]
                        )
                    );

                    continue;
                }
                foreach ($defaultFolder->getAssociationFields() as $associationField) {
                    $criteria->addFilter(
                        new EqualsFilter("media.{$associationField}.id", null)
                    );
                }
            }
        }

        $extension = $context->getExtension(self::RESTRICT_DEFAULT_FOLDER_ENTITIES_EXTENSION);
        if ($extension instanceof ArrayStruct && \is_array($extension->all())) {
            $criteria->addFilter(
                new EqualsAnyFilter('media.mediaFolder.defaultFolder.entity', $extension->all())
            );
        }

        return $criteria;
    }

    private function isNoAssociation(MediaDefaultFolderEntity $defaultFolder): bool
    {
        $folder = $defaultFolder->getFolder();
        \assert($folder !== null);

        $configuration = $folder->getConfiguration();

        \assert($configuration !== null);

        return (bool) $configuration->isNoAssociation();
    }
}
