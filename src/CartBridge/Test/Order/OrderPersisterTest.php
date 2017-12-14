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

namespace Shopware\CartBridge\Test\Order;

use Faker\Factory;
use PHPUnit\Framework\TestCase;
use Shopware\Api\Customer\Struct\CustomerAddressBasicStruct;
use Shopware\Api\Customer\Struct\CustomerBasicStruct;
use Shopware\Api\Order\Repository\OrderRepository;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Tax\TaxDetector;
use Shopware\CartBridge\Order\OrderPersister;
use Shopware\Context\Struct\ShopContext;

class OrderPersisterTest extends TestCase
{
    public function testSave(): void
    {
        $faker = Factory::create();
        $repository = $this->createMock(OrderRepository::class);
        $repository->expects($this->once())->method('create');

        $taxDetector = new TaxDetector();

        $billingAddress = new CustomerAddressBasicStruct();
        $billingAddress->setUuid('SWAG-ADDRESS-UUID-1');
        $billingAddress->setSalutation('mr');
        $billingAddress->setFirstName($faker->firstName);
        $billingAddress->setLastName($faker->lastName);
        $billingAddress->setZipcode($faker->postcode);
        $billingAddress->setCity($faker->city);
        $billingAddress->setCountryUuid('SWAG-AREA-COUNTRY-UUID-1');

        $customer = new CustomerBasicStruct();
        $customer->setUuid('SWAG-CUSTOMER-UUID-1');
        $customer->setDefaultBillingAddress($billingAddress);

        $persister = new OrderPersister($repository, $taxDetector);

        $shopContext = $this->createMock(ShopContext::class);
        $shopContext->expects($this->any())->method('getCustomer')->willReturn($customer);

        $persister->persist(
            $this->createMock(CalculatedCart::class),
            $shopContext
        );
    }
}
