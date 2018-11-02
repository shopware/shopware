<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class OrderRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var OrderPersister
     */
    private $orderPersister;

    /**
     * @var Processor
     */
    private $processor;

    /**
     * @var RepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CheckoutContextFactory
     */
    private $checkoutContextFactory;

    public function setUp()
    {
        $this->repository = $this->getContainer()->get('order.repository');
        $this->orderPersister = $this->getContainer()->get(OrderPersister::class);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->processor = $this->getContainer()->get(Processor::class);
        $this->checkoutContextFactory = $this->getContainer()->get(CheckoutContextFactory::class);
    }

    public function testDeleteOrder()
    {
        $token = Uuid::uuid4()->getHex();
        $cart = new Cart('test', $token);

        $cart->add(
            (new LineItem('test', 'test'))
                ->setLabel('test')
                ->setGood(true)
                ->setPriceDefinition(new AbsolutePriceDefinition(10))
        );

        $customerId = $this->createCustomer();

        $context = $this->checkoutContextFactory->create(
            Defaults::TENANT_ID,
            Uuid::uuid4()->getHex(),
            Defaults::SALES_CHANNEL,
            [
                CheckoutContextService::CUSTOMER_ID => $customerId,
            ]);

        $cart = $this->processor->process($cart, $context);

        $result = $this->orderPersister->persist($cart, $context);

        $orders = $result->getEventByDefinition(OrderDefinition::class);
        $orders = $orders->getIds();
        $id = array_shift($orders);

        $count = $this->getContainer()->get(Connection::class)->fetchAll('SELECT * FROM `order` WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertCount(1, $count);

        $this->repository->delete([
            ['id' => $id],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $count = $this->getContainer()->get(Connection::class)->fetchAll('SELECT * FROM `order` WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertCount(0, $count);
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::uuid4()->getHex();
        $addressId = Uuid::uuid4()->getHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutation' => 'Mr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::uuid4()->getHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => Defaults::COUNTRY,
                    'salutation' => 'Mr',
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->customerRepository->upsert([$customer], Context::createDefaultContext(Defaults::TENANT_ID));

        return $customerId;
    }
}
