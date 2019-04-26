<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Order;

use Faker\Factory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Exception\InvalidCartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class OrderPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;

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
        $cart = new Cart('A', Uuid::randomHex());
        $cart->add(
            (new LineItem('test', 'test'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(static::once())->method('create');

        $persister = new OrderPersister($repository, $this->orderConverter);

        $persister->persist($cart, $this->getSalesChannelContext());
    }

    public function testSaveWithMissingLabel(): void
    {
        $cart = new Cart('A', 'a-b-c');
        $cart->add(
            (new LineItem('test', 'test'))
                ->setPriceDefinition(new AbsolutePriceDefinition(1, 2))
        );

        $processedCart = $this->cartProcessor->process($cart, Generator::createSalesChannelContext(), new CartBehavior());

        $exception = null;
        try {
            $this->orderPersister->persist($processedCart, $this->getSalesChannelContext());
        } catch (InvalidCartException $exception) {
        }

        $messages = [];
        static::assertInstanceOf(InvalidCartException::class, $exception);
        foreach ($exception->getCartErrors() as $error) {
            $messages[] = $error->getMessage();
        }

        static::assertContains('Line item "test" incomplete. Property "label" missing.', $messages);
    }

    private function getCustomer(): CustomerEntity
    {
        $faker = Factory::create();

        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setId('SWAG-ADDRESS-ID-1');
        $billingAddress->setSalutationId($this->getValidSalutationId());
        $billingAddress->setFirstName($faker->firstName);
        $billingAddress->setLastName($faker->lastName);
        $billingAddress->setZipcode($faker->postcode);
        $billingAddress->setCity($faker->city);
        $billingAddress->setCountryId('SWAG-AREA-COUNTRY-ID-1');

        $customer = new CustomerEntity();
        $customer->setId('SWAG-CUSTOMER-ID-1');
        $customer->setDefaultBillingAddress($billingAddress);
        $customer->setEmail('test@example.com');
        $customer->setSalutationId($this->getValidSalutationId());
        $customer->setFirstName($faker->firstName);
        $customer->setLastName($faker->lastName);
        $customer->setCustomerNumber('Test');

        return $customer;
    }

    private function getSalesChannelContext(): MockObject
    {
        $customer = $this->getCustomer();
        $salesChannel = new SalesChannelEntity();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $context = Context::createDefaultContext();
        $salesChannel->setId(Defaults::SALES_CHANNEL);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannelContext->method('getContext')->willReturn($context);

        return $salesChannelContext;
    }
}
