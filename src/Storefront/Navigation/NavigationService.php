<?php declare(strict_types=1);

namespace Shopware\Storefront\Navigation;

use Shopware\Core\Content\Category\CategoryStruct;
use Shopware\Core\Content\Category\Util\Tree\TreeBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermsQuery;

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
        $applicationId = $context->getSourceContext()->getTouchpointId();

        if ($this->navigation[$applicationId]) {
            return $this->navigation[$applicationId];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.parentId', null));
        $criteria->addFilter(new TermQuery('category.active', true));

        $rootCategories = $this->repository->search($criteria, $context);
        $rootIds = [];

        if ($categoryId) {
            $activeCategory = $this->repository->read(new ReadCriteria([$categoryId]), $context)->get($categoryId);

            if ($activeCategory) {
                $rootIds = array_merge($activeCategory->getPathArray(), [$categoryId]);
            }
        }

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('category.parentId', $rootIds));
        $criteria->addFilter(new TermQuery('category.active', 1));

        $leafCategories = $this->repository->search($criteria, $context);

        $activeCategory = $rootCategories->filter(function (CategoryStruct $category) use ($categoryId) {
            return $category->getId() === $categoryId;
        })->first();

        if (!$activeCategory) {
            $activeCategory = $leafCategories->filter(function (CategoryStruct $category) use ($categoryId) {
                return $category->getId() === $categoryId;
            })->first();
        }

        $tree = TreeBuilder::buildTree(null, $rootCategories->getEntities()->sortByPosition()->sortByName());

        foreach ($tree as $index => $rootItem) {
            $rootItem->addChildren(...TreeBuilder::buildTree($rootItem->getCategory()->getId(), $leafCategories->getEntities()->sortByPosition()->sortByName()));
        }

        return $this->navigation[$applicationId] = new Navigation($activeCategory, $tree);
    }
}
