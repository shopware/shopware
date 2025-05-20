<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Order;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Integration\Traits\OrderFixture;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
class SetPaymentOrderRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use OrderFixture;

    private SetPaymentOrderRoute $setPaymentOrderRoute;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setPaymentOrderRoute = static::getContainer()->get(SetPaymentOrderRoute::class);

        $this->customerRepository = static::getContainer()->get('customer.repository');

        $this->orderRepository = static::getContainer()->get('order.repository');
    }

    public function testSetPaymentUpdatePrimary(): void
    {
        $customer = $this->createCustomer();
        static::assertNotNull($customer);

        $orderId = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext(customer: $customer);

        $this->createOrder($orderId, $customer->getId());

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');
        $request->request->set('paymentMethodId', $this->getValidPaymentMethodId());
        $request->request->set('orderId', $orderId);
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $this->setPaymentOrderRoute->setPayment($request, $context);

        $order = $this->orderRepository->search(new Criteria(), $context->getContext())->first();
        static::assertInstanceOf(OrderEntity::class, $order);
        static::assertNotNull($order->getPrimaryOrderTransactionId());
    }

    private function createCustomer(): ?CustomerEntity
    {
        $id1 = Uuid::randomHex();

        $salutationId = $this->getValidSalutationId();

        $this->customerRepository->create([[
            'id' => $id1,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $id1,
                'firstName' => 'not',
                'lastName' => 'not',
                'city' => 'not',
                'street' => 'not',
                'zipcode' => 'not',
                'salutationId' => $salutationId,
                'country' => ['name' => 'not'],
            ],
            'defaultBillingAddressId' => $id1,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'not12345',
            'lastName' => 'not',
            'firstName' => 'First name',
            'salutationId' => $salutationId,
            'customerNumber' => 'not',
        ]], Context::createDefaultContext());

        return $this->customerRepository->search(new Criteria([$id1]), Context::createDefaultContext())->first();
    }

    private function createOrder(string $id, string $customerId): void
    {
        $orderData = $this->getOrderData($id, Context::createDefaultContext())[0];
        $orderData['orderCustomer']['customer']['id'] = $customerId;

        $this->orderRepository->create([$orderData], Context::createDefaultContext());
    }
}
