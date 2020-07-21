<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AssertArraySubsetBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\IpUtils;

class CustomerRemoteAddressSubscriberTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;
    use LineItemTestFixtureBehaviour;
    use AssertArraySubsetBehaviour;

    /**
     * @var KernelBrowser
     */
    private $browser;

    public function setUp(): void
    {
        $this->browser = $this->createCustomSalesChannelBrowser(['id' => Defaults::SALES_CHANNEL]);
        $this->assignSalesChannelContext($this->browser);
    }

    public function testUpdateRemoteAddressByLogin(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';

        $customerId = $this->login($email, $password);

        $remoteAddress = $this->browser->getRequest()->getClientIp();

        $customer = $this->fetchCustomerById($customerId);

        static::assertNotSame($customer->getRemoteAddress(), $remoteAddress);
        static::assertSame($customer->getRemoteAddress(), IpUtils::anonymize($remoteAddress));
    }

    public function testOrderProcessWithRemoteAddress(): void
    {
        $productId = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $email = Uuid::randomHex() . '@shopware.com';
        $password = 'shopware';

        $this->login($email, $password);
        $this->createCart();

        $this->getContainer()->get('product.repository')->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => Uuid::randomHex(), 'name' => 'test'],
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $this->browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $context);

        $this->addProduct($productId);
        $this->order();

        $order = json_decode($this->browser->getResponse()->getContent(), true);
        $orderCustomer = $order['data']['orderCustomer'];

        static::assertSame($email, $orderCustomer['email']);
        static::assertTrue(isset($orderCustomer['remoteAddress']));
        static::assertNotSame($this->browser->getRequest()->getClientIp(), $orderCustomer['remoteAddress']);
        static::assertSame(IpUtils::anonymize($this->browser->getRequest()->getClientIp()), $orderCustomer['remoteAddress']);
    }

    private function addProduct(string $id, int $quantity = 1): void
    {
        $this->browser->request(
            'POST',
            '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart/product/' . $id,
            [
                'quantity' => $quantity,
            ]
        );
    }

    private function order(): void
    {
        $this->browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/order');
    }

    private function createCart(): void
    {
        $this->browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart');
    }

    private function login(string $email, string $password): string
    {
        $customerId = $this->createCustomer($password, $email);

        $this->browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);

        $response = $this->browser->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);

        return $customerId;
    }

    private function createCustomer(string $password, string $email): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->getContainer()->get('customer.repository')->create([
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schoöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'country' => ['name' => 'Germany'],
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ], Context::createDefaultContext());

        return $customerId;
    }

    private function fetchCustomerById(string $customerId): CustomerEntity
    {
        return $this->getContainer()->get('customer.repository')
            ->search(new Criteria([$customerId]), Context::createDefaultContext())
            ->first();
    }
}
