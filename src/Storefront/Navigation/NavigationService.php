<?php declare(strict_types=1);
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

use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Query\TermQuery;
use Shopware\Api\Search\Query\TermsQuery;
use Shopware\Category\Repository\CategoryRepository;
use Shopware\Category\Struct\CategorySearchResult;
use Shopware\Category\Tree\TreeBuilder;
use Shopware\Context\Struct\ShopContext;

class NavigationService
{
    /**
     * @var CategoryRepository
     */
    private $repository;

    public function __construct(CategoryRepository $repository)
    {
        $this->repository = $repository;
    }

    public function load(string $categoryUuid, ShopContext $context): Navigation
    {
        $activeCategory = $this->repository->readBasic([$categoryUuid], $context->getTranslationContext())
            ->get($categoryUuid);

        $systemCategory = $context->getShop()->getCategory();

        $uuids = array_merge($activeCategory->getPathArray(), [$activeCategory->getUuid()]);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('category.parentUuid', $uuids));
        $criteria->addFilter(new TermQuery('category.active', 1));

        /** @var CategorySearchResult $categories */
        $categories = $this->repository->search($criteria, $context->getTranslationContext());

        $tree = TreeBuilder::buildTree(
            $systemCategory->getUuid(),
            $categories->sortByPosition()
        );

        return new Navigation($activeCategory, $tree);
    }
}
