<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\CartBridge\Order;

use Faker\Factory;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Framework\ORM\EntityRepository;

class OrderPersisterTest extends TestCase
{
    public function testSave(): void
    {
        $faker = Factory::create();
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(static::once())->method('create');

        $taxDetector = new TaxDetector();

        $billingAddress = new CustomerAddressStruct();
        $billingAddress->setId('SWAG-ADDRESS-ID-1');
        $billingAddress->setSalutation('mr');
        $billingAddress->setFirstName($faker->firstName);
        $billingAddress->setLastName($faker->lastName);
        $billingAddress->setZipcode($faker->postcode);
        $billingAddress->setCity($faker->city);
        $billingAddress->setCountryId('SWAG-AREA-COUNTRY-ID-1');

        $customer = new CustomerStruct();
        $customer->setId('SWAG-CUSTOMER-ID-1');
        $customer->setDefaultBillingAddress($billingAddress);
        $customer->setEmail('test@example.com');
        $customer->setFirstName($faker->firstName);
        $customer->setLastName($faker->lastName);
        $customer->setCustomerNumber('Test');

        $converter = new OrderConverter($taxDetector);
        $persister = new OrderPersister($repository, $converter);

        $checkoutContext = $this->createMock(CheckoutContext::class);
        $checkoutContext->expects(static::any())->method('getCustomer')->willReturn($customer);

        $cart = new Cart('A', 'a-b-c');
        $cart->add(
            (new LineItem('test', 'test'))
                ->setPrice(new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $persister->persist($cart, $checkoutContext);
    }
}
