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

namespace Shopware\Checkout\CartBridge\Test\Order;

use Faker\Factory;
use PHPUnit\Framework\TestCase;
use Shopware\Checkout\Customer\Struct\CustomerAddressBasicStruct;
use Shopware\Checkout\Customer\Struct\CustomerBasicStruct;
use Shopware\Checkout\Order\Repository\OrderRepository;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Checkout\Cart\Error\ErrorCollection;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItem;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Checkout\Cart\Tax\TaxDetector;
use Shopware\Checkout\CartBridge\Order\OrderPersister;
use Shopware\Context\Struct\StorefrontContext;

class OrderPersisterTest extends TestCase
{
    public function testSave(): void
    {
        $faker = Factory::create();
        $repository = $this->createMock(OrderRepository::class);
        $repository->expects($this->once())->method('create');

        $taxDetector = new TaxDetector();

        $billingAddress = new CustomerAddressBasicStruct();
        $billingAddress->setId('SWAG-ADDRESS-ID-1');
        $billingAddress->setSalutation('mr');
        $billingAddress->setFirstName($faker->firstName);
        $billingAddress->setLastName($faker->lastName);
        $billingAddress->setZipcode($faker->postcode);
        $billingAddress->setCity($faker->city);
        $billingAddress->setCountryId('SWAG-AREA-COUNTRY-ID-1');

        $customer = new CustomerBasicStruct();
        $customer->setId('SWAG-CUSTOMER-ID-1');
        $customer->setDefaultBillingAddress($billingAddress);

        $persister = new OrderPersister($repository, $taxDetector);

        $storefrontContext = $this->createMock(StorefrontContext::class);
        $storefrontContext->expects($this->any())->method('getCustomer')->willReturn($customer);

        $cart = new CalculatedCart(
            new Cart('A', 'a-b-c', new LineItemCollection(), new ErrorCollection()),
            new CalculatedLineItemCollection([
                new CalculatedLineItem(
                    'test',
                    new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    1,
                    'test',
                    'test'
                ),
            ]),
            new CartPrice(1, 1, 1, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_FREE),
            new DeliveryCollection()
        );

        $persister->persist($cart, $storefrontContext);
    }
}
