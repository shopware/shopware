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

namespace Shopware\Cart\Test\Common;

use Shopware\Cart\Delivery\DeliveryDate;
use Shopware\Cart\Delivery\DeliveryInformation;
use Shopware\Cart\LineItem\LineItem;
use Shopware\Cart\Price\Price;
use Shopware\Cart\Product\CalculatedProduct;
use Shopware\Cart\Rule\Container\AndRule;
use Shopware\Cart\Tax\CalculatedTaxCollection;
use Shopware\Cart\Tax\TaxRuleCollection;

class DummyProduct extends CalculatedProduct
{
    public function __construct(string $identifier)
    {
        parent::__construct(
            $identifier,
            1,
            new LineItem('SW1', 'product', 1),
            new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new DeliveryInformation(
                1, 1, 1, 1, 1,
                new DeliveryDate(new \DateTime(), new \DateTime()),
                new DeliveryDate(new \DateTime(), new \DateTime())
            ),
            new AndRule()
        );
    }
}
