<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Storefront\Navigation;

use Shopware\Category\Gateway\CategoryRepository;
use Shopware\Category\Struct\Category;
use Shopware\Context\Struct\ShopContext;
use Shopware\Search\Condition\ActiveCondition;
use Shopware\Search\Condition\CustomerGroupCondition;
use Shopware\Search\Condition\ParentCondition;
use Shopware\Search\Criteria;

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
