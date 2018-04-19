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

namespace Shopware\Tests\Functional\Bundle\SearchBundle\Condition;

use Shopware\Bundle\SearchBundle\Condition\PriceCondition;
use Shopware\Context\Struct\ShopContext;
use Shopware\Models\Category\Category;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class PriceConditionTest extends TestCase
{
    public function testSimplePriceRange()
    {
        $context = $this->getContext();
        $context->setFallbackCustomerGroup($this->getEkCustomerGroup());

        $condition = new PriceCondition(10, 20);

        $this->search(
            [
                'first' => ['EK' => 21],
                'second' => ['EK' => 10],
                'third' => ['EK' => 15],
                'fourth' => ['EK' => 20],
            ],
            ['second', 'third', 'fourth'],
            null,
            [$condition],
            [],
            [],
            $context
        );
    }

    public function testDecimalPriceRange()
    {
        $context = $this->getContext();
        $context->setFallbackCustomerGroup($this->getEkCustomerGroup());
        $condition = new PriceCondition(10, 20);

        $this->search(
            [
                'first' => ['EK' => 9.99],
                'second' => ['EK' => 10.01],
                'third' => ['EK' => 19.98],
            ],
            ['second', 'third'],
            null,
            [$condition],
            [],
            [],
            $context
        );
    }

    public function testCustomerGroupPrices()
    {
        $context = $this->getContext();

        $customerGroup = $this->helper->createCustomerGroup(['key' => 'CUST']);
        $context->setCurrentCustomerGroup(
            $this->converter->convertCustomerGroup($customerGroup)
        );

        $context->setFallbackCustomerGroup($this->getEkCustomerGroup());

        $condition = new PriceCondition(10, 20);

        $this->search(
            [
                'first' => ['EK' => 21],
                'second' => ['EK' => 15],
                'third' => ['EK' => 15, 'CUST' => 5],
                'fourth' => ['EK' => 3,  'CUST' => 15],
            ],
            ['second', 'fourth'],
            null,
            [$condition],
            [],
            [],
            $context
        );
    }

    public function testPriceConditionWithCurrencyFactor()
    {
        $context = $this->getContext();
        $context->getCurrency()->setFactor(1.3625);
        $context->getCurrency()->setId(2);

        $condition = new PriceCondition(12, 29);
        $context->setFallbackCustomerGroup($this->getEkCustomerGroup());

        $this->search(
            [
                'first' => ['EK' => 10],
                'second' => ['EK' => 20],
                'third' => ['EK' => 30],
            ],
            ['first', 'second'],
            null,
            [$condition],
            [],
            [],
            $context
        );
    }

    public function testPriceGroup()
    {
        $condition = new PriceCondition(18, 18);
        $context = $this->getContext();
        $context->setFallbackCustomerGroup($this->getEkCustomerGroup());
        $context->setCurrentCustomerGroup($this->getEkCustomerGroup());

        $priceGroup = $this->helper->createPriceGroup([
            ['key' => 'EK', 'quantity' => 1,  'discount' => 10],
        ]);

        $this->search(
            [
                'first' => ['EK' => 10],

                //20,- € - 10% price group discount = 18,- €
                'second' => ['prices' => ['EK' => 20], 'priceGroup' => $priceGroup],
                'third' => ['EK' => 30],
            ],
            ['second'],
            null,
            [$condition],
            [],
            [],
            $context,
            ['useLastGraduationForCheapestPrice' => false]
        );
    }

    public function testPriceGroupWithLastGraduation()
    {
        $condition = new PriceCondition(14, 14);
        $context = $this->getContext();
        $context->setFallbackCustomerGroup($this->getEkCustomerGroup());
        $context->setCurrentCustomerGroup($this->getEkCustomerGroup());

        $priceGroup = $this->helper->createPriceGroup([
            ['key' => 'EK', 'quantity' => 1,  'discount' => 10],
            ['key' => 'EK', 'quantity' => 10,  'discount' => 30],
        ]);

        $this->search(
            [
                'first' => ['EK' => 10],
                //20,- € - 30% price group discount = 14,- €
                'second' => ['prices' => ['EK' => 20], 'priceGroup' => $priceGroup],
                'third' => ['EK' => 30],
            ],
            ['second'],
            null,
            [$condition],
            [],
            [],
            $context,
            ['useLastGraduationForCheapestPrice' => true]
        );
    }

    /**
     * @param $number
     * @param \Shopware\Models\Category\Category $category
     * @param ShopContext                        $context
     * @param $prices
     *
     * @return array
     */
    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $prices = []
    ) {
        $product = parent::getProduct($number, $context, $category);

        $product['mainDetail']['prices'] = [];
        $productPrices = $prices;
        if (array_key_exists('prices', $prices)) {
            $productPrices = $prices['prices'];
        }

        foreach ($productPrices as $key => $price) {
            if ($key === $context->getCurrentCustomerGroup()->getKey()) {
                $customerGroup = $context->getCurrentCustomerGroup()->getKey();
            } else {
                $customerGroup = $context->getFallbackCustomerGroup()->getKey();
            }

            $product['mainDetail']['prices'][] = [
                 'from' => 1,
                 'to' => 'beliebig',
                 'price' => $price,
                 'customerGroupKey' => $customerGroup,
            ];
        }

        if ($prices['priceGroup']) {
            $product['priceGroupActive'] = true;
            $product['priceGroupId'] = $prices['priceGroup']->getId();
        }

        return $product;
    }
}
