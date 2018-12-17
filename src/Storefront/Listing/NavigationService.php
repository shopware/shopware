<?php declare(strict_types=1);

namespace Shopware\Storefront\Listing;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Util\Tree\TreeBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class NavigationService
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var Navigation[]
     */
    private $navigation;

    public function __construct(RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function load(?string $categoryId, Context $context): ?Navigation
    {
        $applicationId = $context->getSourceContext()->getSalesChannelId();

        if (isset($this->navigation[$applicationId])) {
            return $this->navigation[$applicationId];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.parentId', null));
        $criteria->addFilter(new EqualsFilter('category.active', true));

        /** @var EntitySearchResult $rootCategories */
        $rootCategories = $this->repository->search($criteria, $context);
        $rootIds = [];

        if ($categoryId) {
            /** @var CategoryEntity|null $activeCategory */
            $activeCategory = $this->repository->read(new ReadCriteria([$categoryId]), $context)->get($categoryId);

            if ($activeCategory) {
                $rootIds = array_merge($activeCategory->getPathArray(), [$categoryId]);
            }
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('category.parentId', $rootIds));
        $criteria->addFilter(new EqualsFilter('category.active', 1));

        $leafCategories = $this->repository->search($criteria, $context);

        $activeCategory = $rootCategories->filter(function (CategoryEntity $category) use ($categoryId) {
            return $category->getId() === $categoryId;
        })->first();

        if (!$activeCategory) {
            $activeCategory = $leafCategories->filter(function (CategoryEntity $category) use ($categoryId) {
                return $category->getId() === $categoryId;
            })->first();
        }

        /** @var CategoryCollection $categories */
        $categories = $rootCategories->getEntities();

        $tree = TreeBuilder::buildTree(null, $categories->sortByPosition()->sortByName());

        /** @var CategoryCollection $leaves */
        $leaves = $leafCategories->getEntities();
        $leaves->sortByPosition()->sortByName();

        foreach ($tree as $index => $rootItem) {
            $rootItem->addChildren(...TreeBuilder::buildTree($rootItem->getCategory()->getId(), $leaves));
        }

        return $this->navigation[$applicationId] = new Navigation($activeCategory, $tree);
    }
}
