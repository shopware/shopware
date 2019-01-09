<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class DeleteNotUsedMediaService
{
    /**
     * @var RepositoryInterface
     */
    protected $mediaRepo;

    /**
     * @var RepositoryInterface
     */
    private $defaultFolderRepo;

    public function __construct(RepositoryInterface $mediaRepo, RepositoryInterface $defaultFolderRepo)
    {
        $this->mediaRepo = $mediaRepo;
        $this->defaultFolderRepo = $defaultFolderRepo;
    }

    public function countNotUsedMedia(Context $context): int
    {
        $criteria = $this->createFilterForNotUsedMedia($context);
        $criteria->setLimit(0);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        return $this->mediaRepo->search($criteria, $context)->getTotal();
    }

    public function deleteNotUsedMedia(Context $context): void
    {
        $criteria = $this->createFilterForNotUsedMedia($context);

        $ids = $this->mediaRepo->searchIds($criteria, $context)->getIds();
        $ids = array_map(function ($id) {
            return ['id' => $id];
        }, $ids);
        $this->mediaRepo->delete($ids, $context);
    }

    protected function createFilterForNotUsedMedia(Context $context): Criteria
    {
        $criteria = new Criteria();
        /** @var MediaDefaultFolderCollection $defaultFolders */
        $iterator = new RepositoryIterator($this->defaultFolderRepo, $context);
        while ($defaultFolders = $iterator->fetch()) {
            foreach ($defaultFolders as $defaultFolder) {
                foreach ($defaultFolder->getAssociations() as $association) {
                    $criteria->addFilter(
                        new EqualsFilter("media.${association}.id", null)
                    );
                }
            }
        }

        return $criteria;
    }
}
