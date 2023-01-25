<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Test\Customer\Rule\OrderFixture;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedHook;
use Shopware\Storefront\Page\Account\Order\AccountOrderDetailPageLoadedHook;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoadedHook;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('customer-order')]
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
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('domains.url', $_SERVER['APP_URL']));

        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = $this->getContainer()->get('sales_channel.repository')->search($criteria, $context)->first();

        if ($salesChannel !== null) {
            $orderData[0]['salesChannelId'] = $salesChannel->getId();
        }

        $productId = $this->createProduct($context);
        $orderData[0]['lineItems'][0]['identifier'] = $productId;
        $orderData[0]['lineItems'][0]['productId'] = $productId;

        /** @var EntityRepository $orderRepo */
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
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('domains.url', $_SERVER['APP_URL']));

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

        $browser->followRedirects();

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

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testEditOrderWithDifferentSalesChannelContextShippingMethodRestoresOrderShippingMethod(): void
    {
        $context = Context::createDefaultContext();
        $customer = $this->createCustomer($context);

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['orderCustomer']['customer']['id'] = $customer->getId();
        $orderData[0]['orderCustomer']['customer']['guest'] = false;
        $orderData[0]['orderNumber'] = 'order-number';

        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('domains.url', $_SERVER['APP_URL']));

        /** @var EntityRepository $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = $salesChannelRepository->search($criteria, $context)->first();
        static::assertNotNull($salesChannel);

        if ($salesChannel !== null) {
            $orderData[0]['salesChannelId'] = $salesChannel->getId();
        }

        $productId = $this->createProduct($context);
        $orderData[0]['lineItems'][0]['identifier'] = $productId;
        $orderData[0]['lineItems'][0]['productId'] = $productId;

        $orderRepo = $this->getContainer()->get('order.repository');
        $orderRepo->create($orderData, $context);

        // Change default SalesChannel ShippingMethod to another than the ordered one
        $orderShippingMethodId = $orderData[0]['deliveries'][0]['shippingMethodId'];
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_AND, [
                new EqualsFilter('id', $orderShippingMethodId),
            ]),
            new EqualsFilter('active', true)
        );
        $differentShippingMethodId = $this->getContainer()->get('shipping_method.repository')->searchIds($criteria, $context)->firstId();
        static::assertNotNull($differentShippingMethodId);
        static::assertNotSame($orderShippingMethodId, $differentShippingMethodId);
        $salesChannelRepository->update([
            [
                'id' => $salesChannel->getId(),
                'shippingMethodId' => $differentShippingMethodId,
                'shippingMethods' => [
                    [
                        'id' => $differentShippingMethodId,
                    ],
                    [
                        'id' => $orderShippingMethodId,
                    ],
                ],
            ],
        ], $context);

        $browser = $this->login($customer->getEmail());
        $browser->followRedirects();

        // Load home page to verify the saleschannel got a different shipping method from the ordered one
        $browser->request(
            'GET',
            $_SERVER['APP_URL'] . '/'
        );

        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();
        static::assertNotNull($context = $response->getContext());
        static::assertSame($differentShippingMethodId, $context->getShippingMethod()->getId());

        // Test that the order edit page switches the SalesChannelContext Shipping method to the order one
        $browser->request(
            'GET',
            $_SERVER['APP_URL'] . '/account/order/edit/' . $orderData[0]['id']
        );

        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();
        static::assertNotNull($context = $response->getContext());
        static::assertSame($orderShippingMethodId, $context->getShippingMethod()->getId());
    }

    public function testAccountOrderPageLoadedScriptsAreExecuted(): void
    {
        $context = Context::createDefaultContext();
        $customer = $this->createCustomer($context);
        $browser = $this->login($customer->getEmail());

        $browser->request(
            'GET',
            '/account/order'
        );
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(AccountOrderPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testAccountOrderPageLoadedScriptsAreExecutedForDeeplinkedPage(): void
    {
        $context = Context::createDefaultContext();
        $customer = $this->createCustomer($context);

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['orderCustomer']['customer']['id'] = $customer->getId();
        $orderData[0]['orderCustomer']['customer']['guest'] = false;

        $orderRepo = $this->getContainer()->get('order.repository');
        $orderRepo->create($orderData, $context);

        $browser = $this->login($customer->getEmail());

        $browser->request(
            'GET',
            '/account/order/' . $orderData[0]['deepLinkCode']
        );
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(AccountOrderPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testAccountOrderDetailPageLoadedScriptsAreExecuted(): void
    {
        $context = Context::createDefaultContext();
        $customer = $this->createCustomer($context);

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['orderCustomer']['customer']['id'] = $customer->getId();
        $orderData[0]['orderCustomer']['customer']['guest'] = false;

        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('domains.url', $_SERVER['APP_URL']));

        /** @var EntityRepository $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $salesChannelRepository->search($criteria, $context)->first();
        $orderData[0]['salesChannelId'] = $salesChannel->getId();

        $orderRepo = $this->getContainer()->get('order.repository');
        $orderRepo->create($orderData, $context);

        $browser = $this->login($customer->getEmail());
        $browser->request(
            'GET',
            '/widgets/account/order/detail/' . $orderData[0]['id']
        );
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(AccountOrderDetailPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testAccountOrderEditPageLoadedScriptsAreExecuted(): void
    {
        $context = Context::createDefaultContext();
        $customer = $this->createCustomer($context);

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['orderCustomer']['customer']['id'] = $customer->getId();
        $orderData[0]['orderCustomer']['customer']['guest'] = false;

        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('domains.url', $_SERVER['APP_URL']));

        /** @var EntityRepository $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $salesChannelRepository->search($criteria, $context)->first();
        $orderData[0]['salesChannelId'] = $salesChannel->getId();

        $orderRepo = $this->getContainer()->get('order.repository');
        $orderRepo->create($orderData, $context);

        $browser = $this->login($customer->getEmail());
        $url = '/account/order/edit/' . $orderData[0]['id'];

        $browser->request(
            'GET',
            $url
        );
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $url . $response->getContent());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(AccountEditOrderPageLoadedHook::HOOK_NAME, $traces);
    }

    private function login(string $email): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $email,
                'password' => 'shopware',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $browser;
    }

    private function createCustomer(Context $context, bool $guest = false): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $data = [
            [
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'boundSalesChannelId' => null,
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
                'defaultShippingMethodId' => $this->getValidShippingMethodId(TestDefaults::SALES_CHANNEL),
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(TestDefaults::SALES_CHANNEL),
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'test@example.com',
                'password' => 'shopware',
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
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
        $this->getContainer()->get('product.repository')->create([$data], $context);

        return $productId;
    }
}
