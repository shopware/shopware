<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\CartBridge\Order;

use Faker\Factory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\InvalidCartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Framework\ORM\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class OrderPersisterTest extends TestCase
{
    use KernelTestBehaviour;

    public function testSave(): void
    {
        $checkoutContext = $this->getCheckoutContext();

        $cart = new Cart('A', 'a-b-c');
        $cart->add(
            (new LineItem('test', 'test'))
                ->setPrice(new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(static::once())->method('create');

        $converter = new OrderConverter(new TaxDetector());
        $persister = new OrderPersister($repository, $converter);

        $persister->persist($cart, $checkoutContext);
    }

    public function testSaveWithMissingLabel(): void
    {
        $cartProcessor = $this->getContainer()->get('Shopware\Core\Checkout\Cart\Processor');

        $cart = new Cart('A', 'a-b-c');
        $cart->add(
            (new LineItem('test', 'test'))
                ->setPriceDefinition(new AbsolutePriceDefinition(1))
        );

        $processedCart = $cartProcessor->process($cart, Generator::createContext());

        $repository = $this->createMock(EntityRepository::class);

        $converter = new OrderConverter(new TaxDetector());
        $persister = new OrderPersister($repository, $converter);

        self::expectException(InvalidCartException::class);
        self::expectExceptionMessageRegExp('/.*Line item "test" incomplete\. Property "label" missing\..*/');

        $persister->persist($processedCart, $this->getCheckoutContext());
    }

    private function getCustomer(): CustomerStruct
    {
        $faker = Factory::create();

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

        return $customer;
    }

    private function getCheckoutContext(): MockObject
    {
        $customer = $this->getCustomer();
        $checkoutContext = $this->createMock(CheckoutContext::class);
        $checkoutContext->method('getCustomer')->willReturn($customer);

        return $checkoutContext;
    }
}
