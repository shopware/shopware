<?php

namespace Shopware\Storefront\Navigation;

use Shopware\Category\Struct\Category;
use Shopware\Context\Struct\ShopContext;
use Shopware\Search\Condition\ActiveCondition;
use Shopware\Search\Condition\CustomerGroupCondition;
use Shopware\Search\Condition\ParentCondition;
use Shopware\Search\Criteria;
use Shopware\Category\Gateway\CategoryRepository;

class NavigationLoader
{
    /**
     * @var CategoryRepository
     */
    private $repository;

    public function __construct(CategoryRepository $repository)
    {
        $this->repository = $repository;
    }

    public function load(int $categoryId, ShopContext $context)
    {
        $categories = $this->repository->read([$categoryId], $context->getTranslationContext(), CategoryRepository::FETCH_DETAIL);

        /** @var Category $category */
        $category = $categories->get($categoryId);

        $systemCategory = $context->getShop()->getCategory();

        $criteria = new Criteria();
        $criteria->addCondition(new ParentCondition(array_merge($category->getPath(), [$category->getId()])));
        $criteria->addCondition(new ActiveCondition(true));
        $criteria->addCondition(new CustomerGroupCondition([$context->getCurrentCustomerGroup()->getId()]));

        $result = $this->repository->search($criteria, $context->getTranslationContext());
        $categories = $this->repository->read($result->getIds(), $context->getTranslationContext(), CategoryRepository::FETCH_IDENTITY);

        $tree = $categories->sortByPosition()->getTree($systemCategory->getId());

        return new Navigation($category, $tree);
    }
}