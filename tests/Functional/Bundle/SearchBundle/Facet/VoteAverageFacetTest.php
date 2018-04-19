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

namespace Shopware\Tests\Functional\Bundle\SearchBundle\Facet;

use Shopware\Bundle\SearchBundle\Facet\VoteAverageFacet;
use Shopware\Context\Struct\ShopContext;
use Shopware\Shop\Struct\Shop;
use Shopware\Models\Category\Category;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class VoteAverageFacetTest extends TestCase
{
    public function testVoteAverageFacet()
    {
        $context = $this->getContext(1);

        $result = $this->search(
            [
                'first' => [
                    1 => [1, 2],     //shop = 1    1x vote with 1 point    1x vote with 2 points
                ],
                'second' => [
                    1 => [4, 5],
                ],
                'third' => [
                    1 => [3, 5],
                ],
                'first-2' => [
                    1 => [1, 2],
                ],
                'second-2' => [
                    1 => [4, 5],
                ],
                'third-2' => [
                    1 => [3, 5],
                ],
            ],
            ['first', 'second', 'third', 'first-2', 'second-2', 'third-2'],
            null,
            [],
            [new VoteAverageFacet()],
            [],
            $context,
            []
        );

        $this->assertInstanceOf('Shopware\Bundle\SearchBundle\FacetResult\RadioFacetResult', $result->getFacets()[0]);
    }

    public function testVoteFacetWithoutSubshopVotes()
    {
        $context = $this->getContext(2);

        $result = $this->search(
            [
                'first' => [
                    1 => [1, 2],     //shop = 1    1x vote with 1 point    1x vote with 2 points
                ],
            ],
            ['first'],
            $this->createCategory($context->getShop()),
            [],
            [new VoteAverageFacet()],
            [],
            $context,
            ['displayOnlySubShopVotes' => true]
        );

        $this->assertEmpty($result->getFacets());
    }

    public function testVoteFacetWithSubshopVotes()
    {
        $context = $this->getContext(2);

        $result = $this->search(
            [
                'first' => [
                    2 => [1, 2],     //shop = 1    1x vote with 1 point    1x vote with 2 points
                ],
            ],
            ['first'],
            $this->createCategory($context->getShop()),
            [],
            [new VoteAverageFacet()],
            [],
            $context,
            ['displayOnlySubShopVotes' => true]
        );

        $this->assertInstanceOf('Shopware\Bundle\SearchBundle\FacetResult\RadioFacetResult', $result->getFacets()[0]);
    }

    public function testVoteFacetWithNotAssignedSubShop()
    {
        $context = $this->getContext(2);

        $result = $this->search(
            [
                'first' => [
                    null => [1, 2],      //shop = 1    1x vote with 1 point    1x vote with 2 points
                    1 => [1, 2],         //shop = null    1x vote with 1 point    1x vote with 2 points
                ],
            ],
            ['first'],
            $this->createCategory($context->getShop()),
            [],
            [new VoteAverageFacet()],
            [],
            $context,
            ['displayOnlySubShopVotes' => true]
        );

        $this->assertInstanceOf('Shopware\Bundle\SearchBundle\FacetResult\RadioFacetResult', $result->getFacets()[0]);
    }

    protected function createProduct(
        $number,
        ShopContext $context,
        Category $category,
        $additionally
    ) {
        $article = parent::createProduct(
            $number,
            $context,
            $category,
            $additionally
        );

        foreach ($additionally as $shopId => $votes) {
            if (empty($shopId)) {
                $shopId = null;
            }
            $this->helper->createVotes($article->getId(), $votes, $shopId);
        }

        return $article;
    }

    /**
     * @param \Shopware\Shop\Struct\Shop $shop
     *
     * @return Category
     */
    private function createCategory(Shop $shop)
    {
        $em = Shopware()->Container()->get('models');
        $category = $em->find(Category::class, $shop->getCategory()->getId());

        return $this->helper->createCategory(['parent' => $category]);
    }
}
