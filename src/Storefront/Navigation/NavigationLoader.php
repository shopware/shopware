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

use Shopware\Category\CategoryRepository;
use Shopware\Category\Struct\Category;
use Shopware\Context\Struct\ShopContext;
use Shopware\Search\Condition\ActiveCondition;
use Shopware\Search\Condition\CustomerGroupCondition;
use Shopware\Search\Condition\ParentCondition;
use Shopware\Search\Condition\ParentUuidCondition;
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

    public function load(string $categoryUuid, ShopContext $context)
    {
        $activeCategory = $this->repository->read([$categoryUuid], $context->getTranslationContext())
            ->get($categoryUuid);

        $systemCategory = $context->getShop()->getCategory();

        $criteria = new Criteria();
        $criteria->addCondition(new ParentUuidCondition(array_merge($activeCategory->getPath(), [$activeCategory->getUuid()])));
        $criteria->addCondition(new ActiveCondition(true));

        $categories = $this->repository->search($criteria, $context->getTranslationContext());

        $tree = $categories->sortByPosition()->getTree($systemCategory->getUuid());

        return new Navigation($activeCategory, $tree);
    }
}
