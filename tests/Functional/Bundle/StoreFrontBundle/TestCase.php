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

namespace Shopware\Tests\Functional\Bundle\StoreFrontBundle;

use Shopware\Search\Condition\CategoryCondition;
use Shopware\Search\ConditionInterface;
use Shopware\Search\Criteria;
use Shopware\Search\FacetInterface;
use Shopware\Bundle\SearchBundle\ProductNumberSearchResult;
use Shopware\Search\SortingInterface;
use Shopware\Context\Struct\ShopContext;
use Shopware\Bundle\StoreFrontBundle\Product\BaseProduct;
use Shopware\Models\Article\Article;
use Shopware\Models\Category\Category;

abstract class TestCase extends \Enlight_Components_Test_TestCase
{
    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var Converter
     */
    protected $converter;

    protected function setUp()
    {
        $this->helper = new Helper();
        $this->converter = new Converter();
        parent::setUp();
    }

    protected function tearDown()
    {
        $this->helper->cleanUp();
        parent::tearDown();
    }

    /**
     * @param $products
     * @param \Shopware\Context\Struct\ShopContext $context
     * @param Category                                              $category
     *
     * @return Article[]
     */
    public function createProducts($products, ShopContext $context, Category $category)
    {
        $articles = [];
        foreach ($products as $number => $additionally) {
            $articles[] = $this->createProduct(
                $number,
                $context,
                $category,
                $additionally
            );
        }

        return $articles;
    }

    /**
     * @return \Shopware\CustomerGroup\Struct\CustomerGroup
     */
    public function getEkCustomerGroup()
    {
        return $this->converter->convertCustomerGroup(
            Shopware()->Container()->get('models')->find('Shopware\Models\Customer\Group', 1)
        );
    }

    /**
     * @param array                $products
     * @param array                $expectedNumbers
     * @param Category             $category
     * @param \Shopware\Search\ConditionInterface[] $conditions
     * @param \Shopware\Search\FacetInterface[]     $facets
     * @param \Shopware\Search\SortingInterface[]   $sortings
     * @param null                 $context
     * @param array                $configs
     *
     * @return ProductNumberSearchResult
     */
    protected function search(
        $products,
        $expectedNumbers,
        $category = null,
        $conditions = [],
        $facets = [],
        $sortings = [],
        $context = null,
        array $configs = []
    ) {
        if ($context === null) {
            $context = $this->getContext();
        }

        if ($category === null) {
            $category = $this->helper->createCategory();
        }

        $config = Shopware()->Container()->get('config');
        $originals = [];
        foreach ($configs as $key => $value) {
            $originals[$key] = $config->get($key);
            $config->offsetSet($key, $value);
        }

        $this->createProducts($products, $context, $category);

        $this->helper->refreshSearchIndexes($context->getShop());

        $criteria = new Criteria();

        $this->addCategoryBaseCondition($criteria, $category, $conditions, $context);

        $this->addConditions($criteria, $conditions);

        $this->addFacets($criteria, $facets);

        $this->addSortings($criteria, $sortings);

        $criteria->offset(0)->limit(4000);

        $search = Shopware()->Container()->get('shopware_search.product_number_search');

        $result = $search->search($criteria, $context);

        foreach ($originals as $key => $value) {
            $config->offsetSet($key, $value);
        }

        $this->assertSearchResult($result, $expectedNumbers);

        return $result;
    }

    /**
     * @param \Shopware\Search\Criteria $criteria
     * @param Category $category
     * @param $conditions
     * @param \Shopware\Context\Struct\ShopContext $context
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
        }
    }

    /**
     * @param \Shopware\Search\Criteria             $criteria
     * @param \Shopware\Search\ConditionInterface[] $conditions
     */
    protected function addConditions(Criteria $criteria, $conditions)
    {
        foreach ($conditions as $condition) {
            $criteria->addCondition($condition);
        }
    }

    /**
     * @param \Shopware\Search\Criteria         $criteria
     * @param \Shopware\Search\FacetInterface[] $facets
     */
    protected function addFacets(Criteria $criteria, $facets)
    {
        foreach ($facets as $facet) {
            $criteria->addFacet($facet);
        }
    }

    /**
     * @param Criteria           $criteria
     * @param \Shopware\Search\SortingInterface[] $sortings
     */
    protected function addSortings(Criteria $criteria, $sortings)
    {
        foreach ($sortings as $sorting) {
            $criteria->addSorting($sorting);
        }
    }

    /**
     * @param $number
     * @param ShopContext $context
     * @param Category    $category
     * @param $additionally
     *
     * @return Article
     */
    protected function createProduct(
        $number,
        ShopContext $context,
        Category $category,
        $additionally
    ) {
        $data = $this->getProduct(
            $number,
            $context,
            $category,
            $additionally
        );

        return $this->helper->createArticle($data);
    }

    /**
     * @param ProductNumberSearchResult $result
     * @param $expectedNumbers
     */
    protected function assertSearchResult(
        ProductNumberSearchResult $result,
        $expectedNumbers
    ) {
        $numbers = array_map(function (BaseProduct $product) {
            return $product->getNumber();
        }, $result->getProducts());

        foreach ($numbers as $number) {
            $this->assertContains($number, $expectedNumbers, sprintf('Product with number: `%s` found but not expected', $number));
        }
        foreach ($expectedNumbers as $number) {
            $this->assertContains($number, $numbers, sprintf('Expected product number: `%s` not found', $number));
        }

        $this->assertCount(count($expectedNumbers), $result->getProducts());
        $this->assertEquals(count($expectedNumbers), $result->getTotalCount());
    }

    protected function assertSearchResultSorting(
        ProductNumberSearchResult $result,
        $expectedNumbers
    ) {
        $productResult = array_values($result->getProducts());

        /** @var \Shopware\Bundle\StoreFrontBundle\Product\BaseProduct $product */
        foreach ($productResult as $index => $product) {
            $expectedProduct = $expectedNumbers[$index];

            $this->assertEquals(
                $expectedProduct,
                $product->getNumber(),
                sprintf(
                    'Expected %s at search result position %s, but got product %s',
                    $expectedProduct, $index, $product->getNumber()
                )
            );
        }
    }

    /**
     * @param int $shopId
     *
     * @return TestContext
     */
    protected function getContext($shopId = 1)
    {
        $tax = $this->helper->createTax();
        $customerGroup = $this->helper->createCustomerGroup();

        $shop = $this->helper->getShop($shopId);

        return $this->helper->createContext(
            $customerGroup,
            $shop,
            [$tax]
        );
    }

    /**
     * @param $number
     * @param \Shopware\Context\Struct\ShopContext $context
     * @param Category                                              $category
     * @param array                                                 $additionally
     *
     * @return array
     */
    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $additionally = []
    ) {
        $product = $this->helper->getSimpleProduct(
            $number,
            array_shift($context->getTaxRules()),
            $context->getCurrentCustomerGroup()
        );
        $product['categories'] = [['id' => $context->getShop()->getCategory()->getId()]];

        if ($category) {
            $product['categories'] = [
                ['id' => $category->getId()],
            ];
        }

        if (!is_array($additionally)) {
            $additionally = [];
        }

        $product = array_merge($product, $additionally);

        return $product;
    }
}
