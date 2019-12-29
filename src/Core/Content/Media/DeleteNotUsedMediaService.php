<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

class DeleteNotUsedMediaService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepo;

    /**
     * @var EntityRepositoryInterface
     */
    private $defaultFolderRepo;

    public function __construct(EntityRepositoryInterface $mediaRepo, EntityRepositoryInterface $defaultFolderRepo)
    {
        $this->mediaRepo = $mediaRepo;
        $this->defaultFolderRepo = $defaultFolderRepo;
    }

    public function countNotUsedMedia(Context $context): int
    {
        $criteria = $this->createFilterForNotUsedMedia($context);
        $criteria->setLimit(1);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        return $this->mediaRepo->search($criteria, $context)->getTotal();
    }

    public function deleteNotUsedMedia(Context $context): void
    {
        $criteria = $this->createFilterForNotUsedMedia($context);

        $ids = $this->mediaRepo->searchIds($criteria, $context)->getIds();
        $ids = array_map(static function ($id) {
            return ['id' => $id];
        }, $ids);
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
                if ($defaultFolder->getFolder()->getConfiguration()->isNoAssociation()) {
                    $criteria->addFilter(
                        new MultiFilter(
                            'OR',
                            [
                                new NotFilter('AND', [
                                    new EqualsFilter('mediaFolderId', $defaultFolder->getFolder()->getId()),
                                ]),
                                new EqualsFilter('mediaFolderId', null),
                            ]
                        )
                    );

                    continue;
                }
                foreach ($defaultFolder->getAssociationFields() as $associationField) {
                    $criteria->addFilter(
                        new EqualsFilter("media.${associationField}.id", null)
                    );
                }
            }
        }

        return $criteria;
    }
}
