<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Test\Customer\Rule\OrderFixture;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class AccountOrderControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use OrderFixture;
    use StorefrontControllerTestBehaviour;
    use CountryAddToSalesChannelTestBehaviour;

    protected function setUp(): void
    {
        $this->addCountriesToSalesChannel();
    }

    public function testAjaxOrderDetail(): void
    {
        $context = Context::createDefaultContext();
        $customer = $this->createCustomer($context);
        $browser = $this->login($customer->getEmail());

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['orderCustomer']['customer'] = ['id' => $customer->getId()];

        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))
            ->addFilter(new EqualsFilter('active', true));

        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = $this->getContainer()->get('sales_channel.repository')->search($criteria, $context)->first();
        if ($salesChannel !== null) {
            $orderData[0]['salesChannelId'] = $salesChannel->getId();
        }

        $productId = $this->createProduct($context);
        $orderData[0]['lineItems'][0]['identifier'] = $productId;
        $orderData[0]['lineItems'][0]['productId'] = $productId;

        $orderRepo = $this->getContainer()->get('order.repository');
        $orderRepo->create($orderData, $context);

        $browser->request('GET', $_SERVER['APP_URL'] . '/widgets/account/order/detail/' . $orderId);
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();

        /** @var OrderLineItemCollection $orderLineItemCollection */
        $orderLineItemCollection = $response->getData()['orderDetails'];

        foreach ($orderLineItemCollection as $orderLineItemEntity) {
            static::assertNull($orderLineItemEntity->getProduct());
        }

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(OrderRouteRequestEvent::class, static function (OrderRouteRequestEvent $event): void {
            $event->getCriteria()->addAssociation('lineItems.product');
        });

        $browser->request('GET', $_SERVER['APP_URL'] . '/widgets/account/order/detail/' . $orderId);
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();

        /** @var OrderLineItemCollection $orderLineItemCollection */
        $orderLineItemCollection = $response->getData()['orderDetails'];

        foreach ($orderLineItemCollection as $orderLineItemEntity) {
            static::assertNotNull($orderLineItemEntity->getProduct());
        }
    }

    public function testGuestCustomerGetsRedirectedToAuth(): void
    {
        $context = Context::createDefaultContext();
        $customer = $this->createCustomer($context, true);
        $browser = $this->login($customer->getEmail());

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['orderCustomer']['customer']['id'] = $customer->getId();
        $orderData[0]['orderNumber'] = 'order-number';

        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))
            ->addFilter(new EqualsFilter('active', true));

        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = $this->getContainer()->get('sales_channel.repository')->search($criteria, $context)->first();
        if ($salesChannel !== null) {
            $orderData[0]['salesChannelId'] = $salesChannel->getId();
        }

        $productId = $this->createProduct($context);
        $orderData[0]['lineItems'][0]['identifier'] = $productId;
        $orderData[0]['lineItems'][0]['productId'] = $productId;

        $orderRepo = $this->getContainer()->get('order.repository');
        $orderRepo->create($orderData, $context);

        $browser->followRedirects(true);

        $browser->request('GET', $_SERVER['APP_URL'] . '/account/order/' . $orderData[0]['deepLinkCode']);
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();

        static::assertSame('frontend.account.order.single.page', $response->getData()['redirectTo']);
        static::assertSame('BwvdEInxOHBbwfRw6oHF1Q_orfYeo9RY', $response->getData()['redirectParameters']['deepLinkCode']);

        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/order/' . $orderData[0]['deepLinkCode'],
            $this->tokenize('frontend.account.login', [
                'email' => $customer->getEmail(),
                'zipcode' => $orderData[0]['orderCustomer']['customer']['addresses'][0]['zipcode'],
            ])
        );

        static::assertSame(200, $response->getStatusCode(), $response->getContent());
    }

    private function login(string $email): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $email,
                'password' => 'test',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        return $browser;
    }

    private function createCustomer(Context $context, bool $guest = false): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $data = [
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'guest' => $guest,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'test@example.com',
                'password' => 'test',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, $context);

        return $repo->search(new Criteria([$customerId]), $context)->first();
    }

    private function createProduct(Context $context): string
    {
        $productId = Uuid::randomHex();

        $productNumber = Uuid::randomHex();
        $data = [
            'id' => $productId,
            'productNumber' => $productNumber,
            'stock' => 1,
            'name' => 'Test Product',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 11.99, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'taxId' => $this->getValidTaxId(),
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
        $this->getContainer()->get('product.repository')->create([$data], $context);

        return $productId;
    }
}
