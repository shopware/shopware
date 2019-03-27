<?php declare(strict_types=1);

namespace Shopware\Core\Content\Navigation\Service;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Navigation\Exception\NavigationNotFoundException;
use Shopware\Core\Content\Navigation\NavigationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Util\Tree\Tree;
use Shopware\Core\Framework\DataAbstractionLayer\Util\Tree\TreeBuilder;

class NavigationTreeLoader
{
    /** @var EntityRepositoryInterface $navigationRepository */
    private $navigationRepository;

    public function __construct(EntityRepositoryInterface $repository)
    {
        $this->navigationRepository = $repository;
    }

    /**
     * returns category tree while
     * only the active navigation entry has children
     *
     * @throws NavigationNotFoundException
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     * @throws \Shopware\Core\Framework\Exception\InvalidParameterException
     */
    public function read(string $activeId, CheckoutContext $context): ?Tree
    {
        /** @var NavigationEntity $active */
        $active = $this->getActiveCategory($activeId, $context);
        $parentIds = $this->getParentIds($active, $activeId);
        $rootId = $this->getRootId($active, $activeId);

        $criteria = new Criteria();
        $filters = $criteria->getFilters();
        $criteria->addFilter(new EqualsAnyFilter('navigation.parentId', $parentIds));

        $navigations = $this->navigationRepository->search($criteria, $context->getContext());
        $navigations->add($active);

        return $this->buildTree($rootId, $navigations, $active);
    }

    /**
     * returns complete category tree
     * with all children
     *
     * @throws NavigationNotFoundException
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     * @throws \Shopware\Core\Framework\Exception\InvalidParameterException
     */
    public function getCategories(string $activeId, CheckoutContext $context): ?Tree
    {
        /** @var NavigationEntity $active */
        $active = $this->getActiveCategory($activeId, $context);
        $rootId = $this->getRootId($active, $activeId);

        $navigations = $this->navigationRepository->search(new Criteria(), $context->getContext());

        return $this->buildTree($rootId, $navigations, $active);
    }

    /**
     * returns a single category
     * with all its children
     *
     * @throws NavigationNotFoundException
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     * @throws \Shopware\Core\Framework\Exception\InvalidParameterException
     */
    public function getCategory(string $navigationId, string $activeId, CheckoutContext $context): ?Tree
    {
        /** @var NavigationEntity $active */
        $active = $this->getActiveCategory($navigationId, $context);
        $rootId = $this->getRootId($active, $navigationId);

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new EqualsFilter('navigation.id', $navigationId),
                new EqualsFilter('navigation.parentId', $navigationId),
            ]
        ));

        $navigations = $this->navigationRepository->search($criteria, $context->getContext());
        $parentId = $navigations->get($navigationId)->getParentId();

        return $this->buildTree($parentId, $navigations, $active);
    }

    /**
     * @throws \Shopware\Core\Framework\Exception\InvalidParameterException
     */
    private function buildTree(?string $parentId, EntitySearchResult $navigations, NavigationEntity $active): Tree
    {
        $tree = TreeBuilder::buildTree($parentId, $navigations);

        return new Tree($active, $tree);
    }

    /**
     * @throws NavigationNotFoundException
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function getActiveCategory(string $activeId, CheckoutContext $context): NavigationEntity
    {
        $criteria = new Criteria([$activeId]);

        $active = $this->navigationRepository
            ->search($criteria, $context->getContext())
            ->first();

        if (!$active) {
            throw new NavigationNotFoundException($activeId);
        }

        return $active;
    }

    private function getParentIds(NavigationEntity $active, string $activeId): array
    {
        if ($active->getPath() !== null) {
            $path = array_filter(explode('|', $active->getPath()));

            return array_merge([$activeId], $path);
        }

        return [$activeId];
    }

    private function getRootId(NavigationEntity $active, string $activeId): string
    {
        if ($active->getPath() !== null) {
            $path = array_filter(explode('|', $active->getPath()));

            return array_shift($path);
        }

        return $activeId;
    }
}
