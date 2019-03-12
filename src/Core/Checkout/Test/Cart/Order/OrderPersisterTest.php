<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Order;

use Faker\Factory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehaviorContext;
use Shopware\Core\Checkout\Cart\Exception\InvalidCartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class OrderPersisterTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var OrderPersister
     */
    private $orderPersister;

    /**
     * @var Processor
     */
    private $cartProcessor;

    /**
     * @var OrderConverter
     */
    private $orderConverter;

    protected function setUp(): void
    {
        $this->orderPersister = $this->getContainer()->get(OrderPersister::class);
        $this->cartProcessor = $this->getContainer()->get(Processor::class);
        $this->orderConverter = $this->getContainer()->get(OrderConverter::class);
    }

    public function testSave(): void
    {
        $cart = new Cart('A', Uuid::uuid4()->getHex());
        $cart->add(
            (new LineItem('test', 'test'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(static::once())->method('create');

        $persister = new OrderPersister($repository, $this->orderConverter);

        $persister->persist($cart, $this->getCheckoutContext());
    }

    public function testSaveWithMissingLabel(): void
    {
        $cart = new Cart('A', 'a-b-c');
        $cart->add(
            (new LineItem('test', 'test'))
                ->setPriceDefinition(new AbsolutePriceDefinition(1))
        );

        $processedCart = $this->cartProcessor->process($cart, Generator::createCheckoutContext(), new CartBehaviorContext());

        $this->expectException(InvalidCartException::class);
        $this->expectExceptionMessageRegExp('/.*Line item "test" incomplete\. Property "label" missing\..*/');

        $this->orderPersister->persist($processedCart, $this->getCheckoutContext());
    }

    private function getCustomer(): CustomerEntity
    {
        $faker = Factory::create();

        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setId('SWAG-ADDRESS-ID-1');
        $billingAddress->setSalutation('mr');
        $billingAddress->setFirstName($faker->firstName);
        $billingAddress->setLastName($faker->lastName);
        $billingAddress->setZipcode($faker->postcode);
        $billingAddress->setCity($faker->city);
        $billingAddress->setCountryId('SWAG-AREA-COUNTRY-ID-1');

        $customer = new CustomerEntity();
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
        $salesChannel = new SalesChannelEntity();
        $checkoutContext = $this->createMock(CheckoutContext::class);
        $checkoutContext->method('getCustomer')->willReturn($customer);

        $context = Context::createDefaultContext();
        $salesChannel->setId($context->getSourceContext()->getSalesChannelId());
        $checkoutContext->method('getSalesChannel')->willReturn($salesChannel);
        $checkoutContext->method('getContext')->willReturn($context);

        return $checkoutContext;
    }
}
