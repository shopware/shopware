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

use Shopware\Bundle\SearchBundle\Condition\ManufacturerCondition;
use Shopware\Context\Struct\ShopContext;
use Shopware\Models\Article\Supplier;
use Shopware\Models\Category\Category;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class ManufacturerConditionTest extends TestCase
{
    public function testSingleManufacturer()
    {
        $manufacturer = $this->helper->createManufacturer();
        $condition = new ManufacturerCondition([$manufacturer->getId()]);

        $this->search(
            [
                'first' => $manufacturer,
                'second' => $manufacturer,
                'third' => null,
            ],
            ['first', 'second'],
            null,
            [$condition]
        );
    }

    public function testMultipleManufacturers()
    {
        $manufacturer = $this->helper->createManufacturer();
        $second = $this->helper->createManufacturer();

        $condition = new ManufacturerCondition([
            $manufacturer->getId(),
            $second->getId(),
        ]);

        $this->search(
            [
                'first' => $manufacturer,
                'second' => $second,
                'third' => null,
            ],
            ['first', 'second'],
            null,
            [$condition]
        );
    }

    /**
     * @param $number
     * @param \Shopware\Models\Category\Category                    $category
     * @param Supplier                                              $manufacturer
     * @param \Shopware\Context\Struct\ShopContext $context
     *
     * @return array
     */
    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $manufacturer = null
    ) {
        $product = parent::getProduct($number, $context, $category);

        if ($manufacturer) {
            $product['supplierId'] = $manufacturer->getId();
        }

        return $product;
    }
}
