<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class NavigationLoader
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var TreeItem
     */
    private $treeItem;

    public function __construct(SalesChannelRepositoryInterface $repository)
    {
        $this->categoryRepository = $repository;
        $this->treeItem = new TreeItem(null, []);
    }

    /**
     * Returns the full category tree. The provided active id will be marked as selected
     *
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function load(string $activeId, SalesChannelContext $context, string $rootId): Tree
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('id', $activeId),
                new EqualsFilter('parentId', $rootId),
            ])
        );
        $criteria->addFilter(new EqualsFilter('category.visible', true));
        $criteria->addAssociation('media');

        /** @var CategoryCollection $rootLevel */
        $rootLevel = $this->categoryRepository->search($criteria, $context)->getEntities();

        $active = $rootLevel->get($activeId);
        if (!$active) {
            throw new CategoryNotFoundException($activeId);
        }

        $ids = $rootLevel->getIds();
        $ids = array_flip($ids);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('parentId', $ids));
        $criteria->addFilter(new EqualsFilter('category.visible', true));

        /** @var CategoryCollection $secondLevel */
        $secondLevel = $this->categoryRepository->search($criteria, $context)->getEntities();

        foreach ($secondLevel as $category) {
            $rootLevel->add($category);
        }

        return $this->getTree($rootId, $rootLevel, $active);
    }

    /**
     * Returns the category tree level for the provided category id.
     *
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function loadLevel(string $categoryId, SalesChannelContext $context): Tree
    {
        /** @var CategoryEntity $active */
        $active = $this->loadActive($categoryId, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('visible', true));
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new EqualsFilter('category.id', $categoryId),
                new EqualsFilter('category.parentId', $categoryId),
            ]
        ));

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search($criteria, $context)->getEntities();
        $parentId = $categories->get($categoryId)->getParentId();

        return $this->getTree($parentId, $categories, $active);
    }

    private function getTree(?string $parentId, CategoryCollection $categories, CategoryEntity $active): Tree
    {
        $tree = $this->buildTree($parentId, $categories->getElements());

        return new Tree($active, $tree);
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    private function loadActive(string $activeId, SalesChannelContext $context): CategoryEntity
    {
        $criteria = new Criteria([$activeId]);
        $criteria->addAssociation('media');

        $active = $this->categoryRepository->search($criteria, $context)->first();
        if (!$active) {
            throw new CategoryNotFoundException($activeId);
        }

        return $active;
    }

    /**
     * @param CategoryEntity[] $categories
     *
     * @return TreeItem[]
     */
    private function buildTree(?string $parentId, array $categories): array
    {
        $children = new CategoryCollection();
        foreach ($categories as $key => $category) {
            if ($category->getParentId() !== $parentId) {
                continue;
            }

            unset($categories[$key]);

            $children->add($category);
        }

        $children->sortByPosition();

        $items = [];
        foreach ($children as $child) {
            $item = clone $this->treeItem;
            $item->setCategory($child);

            $item->setChildren(
                $this->buildTree($child->getId(), $categories)
            );

            $items[$child->getId()] = $item;
        }

        return $items;
    }
}
