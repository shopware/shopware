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

use Shopware\Search\Condition\CategoryCondition;
use Shopware\Search\Criteria;
use Shopware\Bundle\SearchBundle\Facet\CategoryFacet;
use Shopware\Bundle\SearchBundle\FacetResult\TreeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\TreeItem;
use Shopware\Context\Struct\ShopContext;
use Shopware\Models\Category\Category;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class CategoryFacetTest extends TestCase
{
    public function testSingleProductInFacet()
    {
        $baseCategory = $this->helper->createCategory([
            'name' => 'firstLevel',
        ]);

        $subCategory = $this->helper->createCategory([
            'name' => 'secondLevel',
            'parent' => $baseCategory->getId(),
        ]);

        $result = $this->search(
            [
                'first' => $baseCategory,
                'second' => $subCategory,
                'third' => $subCategory,
                'fourth' => null,
            ],
            ['first', 'second', 'third'],
            $baseCategory,
            [],
            [new CategoryFacet()]
        );

        $this->assertCount(1, $result->getFacets());

        $facet = $result->getFacets();
        $facet = $facet[0];

        /* @var $facet TreeFacetResult */
        $this->assertInstanceOf('Shopware\Bundle\SearchBundle\FacetResult\TreeFacetResult', $facet);

        $this->assertCount(1, $facet->getValues());

        /** @var TreeItem $value */
        $value = $facet->getValues()[0];
        $this->assertEquals('firstLevel', $value->getLabel());
    }

    public function testMultipleCategories()
    {
        $baseCategory = $this->helper->createCategory([
            'name' => 'firstLevel',
        ]);

        $subCategory1 = $this->helper->createCategory([
            'name' => 'secondLevel-1',
            'parent' => $baseCategory->getId(),
        ]);
        $subCategory2 = $this->helper->createCategory([
            'name' => 'secondLevel-2',
            'parent' => $baseCategory->getId(),
        ]);

        $result = $this->search(
            [
                'first' => $subCategory1,
                'second' => $subCategory1,
                'third' => $subCategory2,
                'fourth' => $subCategory2,
                'fifth' => $subCategory2,
            ],
            ['first', 'second', 'third', 'fourth', 'fifth'],
            $baseCategory,
            [],
            [new CategoryFacet()]
        );

        $facet = $result->getFacets();
        $facet = $facet[0];

        /* @var $facet TreeFacetResult */
        $this->assertInstanceOf('Shopware\Bundle\SearchBundle\FacetResult\TreeFacetResult', $facet);

        $this->assertCount(1, $facet->getValues());

        $value = $facet->getValues()[0];
        $this->assertEquals('firstLevel', $value->getLabel());
        $this->assertTrue($value->isActive());

        $this->assertEquals('secondLevel-1', $value->getValues()[0]->getLabel());
        $this->assertEquals('secondLevel-2', $value->getValues()[1]->getLabel());
    }

    public function testNestedCategories()
    {
        $baseCategory = $this->helper->createCategory([
            'name' => 'firstLevel',
        ]);

        $subCategory1 = $this->helper->createCategory([
            'name' => 'secondLevel-1',
            'parent' => $baseCategory->getId(),
        ]);

        $subCategory2 = $this->helper->createCategory([
            'name' => 'thirdLevel-2',
            'parent' => $subCategory1->getId(),
        ]);

        $subCategory3 = $this->helper->createCategory([
            'name' => 'secondLevel-2',
            'parent' => $baseCategory->getId(),
        ]);

        $result = $this->search(
            [
                'first' => $subCategory1,
                'second' => $subCategory1,
                'third' => $subCategory2,
                'fourth' => $subCategory3,
                'fifth' => $subCategory3,
            ],
            ['first', 'second', 'third'],
            $subCategory1,
            [],
            [new CategoryFacet(null, 4)]
        );

        $facet = $result->getFacets();
        $facet = $facet[0];

        /* @var $facet TreeFacetResult */
        $this->assertInstanceOf('Shopware\Bundle\SearchBundle\FacetResult\TreeFacetResult', $facet);

        $this->assertCount(1, $facet->getValues());

        /** @var TreeItem $value */
        $value = $facet->getValues()[0];
        $this->assertEquals('firstLevel', $value->getLabel());

        $value = $value->getValues()[0];
        $this->assertEquals('secondLevel-1', $value->getLabel());
        $this->assertTrue($value->isActive());

        $value = $value->getValues()[0];
        $this->assertEquals('thirdLevel-2', $value->getLabel());
    }

    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $additionally = null
    ) {
        return parent::getProduct($number, $context, $additionally);
    }

    /**
     * @param \Shopware\Search\Criteria $criteria
     * @param Category $category
     * @param $conditions
     * @param ShopContext $context
     */
    protected function addCategoryBaseCondition(
        Criteria $criteria,
        Category $category,
        $conditions,
        ShopContext $context
    ) {
        if ($category) {
            $criteria->addBaseCondition(
                new CategoryCondition([$category->getId()])
            );
            $criteria->addCondition(
                new CategoryCondition([$category->getId()])
            );
        }
    }
}
