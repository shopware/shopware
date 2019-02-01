<?php

namespace Shopware\Core\Content\Navigation\DataAbstractionLayer;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Navigation\NavigationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

class NavigationSynchronizer implements IndexerInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $navigationRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    public function index(\DateTime $timestamp): void
    {

    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $categoryIds = $this->getCategoryIds($event);

        if (!$categoryIds) {
            return;
        }

        $context = $event->getContext();

        $categories = $this->categoryRepository->search(
            new Criteria($categoryIds),
            $context
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('navigation.categoryId', $categoryIds));

        $navigations = $this->navigationRepository->search($criteria, $context);



    }

    private function getCategoryIds(EntityWrittenContainerEvent $event)
    {
        $categories = $event->getEventByDefinition(CategoryDefinition::class);

        if (!$categories) {
            return null;
        }

        return $categories->getIds();
    }
}