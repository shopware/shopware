<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @see https://github.com/shopware/shopware/issues/20225
 */
#[Package('checkout')]
#[Group('store-api')]
class LoginWithInvalidDefaultAddressTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private Connection $connection;

    private string $email;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->browser = $this->createCustomSalesChannelBrowser(['id' => $this->ids->create('sales-channel')]);
        $this->assignSalesChannelContext($this->browser);
        $this->connection = static::getContainer()->get(Connection::class);
        $this->email = Uuid::randomHex() . '@example.com';
    }

    public function testLoginSucceedsWhenDefaultBillingAddressIsMissing(): void
    {
        $this->createCustomer();
        $this->deleteAddress('billing-address');

        static::assertSame(200, $this->login());
    }

    public function testLoginSucceedsWhenDefaultShippingAddressIsMissing(): void
    {
        $this->createCustomer();
        $this->deleteAddress('shipping-address');

        static::assertSame(200, $this->login());
    }

    public function testLoginSucceedsWhenBothDefaultAddressesAreMissing(): void
    {
        $this->createCustomer();
        $this->deleteAddress('billing-address');
        $this->deleteAddress('shipping-address');

        static::assertSame(200, $this->login());
    }

    public function testCartIsBlockedAndOrderIsRefusedWhenDefaultBillingAddressIsMissing(): void
    {
        $this->createCustomer();
        $this->createProduct(ProductDefinition::TYPE_PHYSICAL);
        $this->deleteAddress('billing-address');
        static::assertSame(200, $this->login());

        $errors = $this->addProductToCart();

        static::assertArrayHasKey('billing-address-missing', $errors);
        static::assertTrue($errors['billing-address-missing']['block']);
        static::assertNotSame(
            'checkout.billing-address-missing',
            $errors['billing-address-missing']['translatedMessage'],
            'the snippet must resolve, otherwise the raw key is shown to the customer'
        );

        $this->assertOrderIsRefused();
    }

    public function testDigitalOnlyCartIsRefusedWhenDefaultShippingAddressIsMissing(): void
    {
        $this->createCustomer();
        $this->createProduct(ProductDefinition::TYPE_DIGITAL);
        $this->deleteAddress('shipping-address');
        static::assertSame(200, $this->login());

        $errors = $this->addProductToCart();

        static::assertArrayHasKey('shipping-address-missing', $errors);
        static::assertTrue($errors['shipping-address-missing']['block']);

        // a digital-only cart creates no delivery, so only the validator can stop the order
        $this->assertOrderIsRefused();
    }

    public function testCustomerCanRepairTheMissingDefaultAddress(): void
    {
        $this->createCustomer();
        $this->createProduct(ProductDefinition::TYPE_PHYSICAL);
        $this->deleteAddress('billing-address');
        $this->deleteAddress('shipping-address');
        static::assertSame(200, $this->login());

        static::assertNotEmpty($this->addProductToCart());

        $this->browser->request('POST', '/store-api/account/address', [
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'street' => 'Musterstraße 2',
            'city' => 'Schöppingen',
            'zipcode' => '12345',
            'salutationId' => $this->getValidSalutationId(),
            'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
        ]);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        // the route mints its own id
        $addressId = $this->decodeResponse()['id'] ?? null;
        static::assertIsString($addressId);

        foreach (['default-billing', 'default-shipping'] as $type) {
            $this->browser->request('PATCH', '/store-api/account/address/' . $type . '/' . $addressId);
            static::assertSame(204, $this->browser->getResponse()->getStatusCode());
        }

        static::assertSame([], $this->addProductToCart());
    }

    private function login(): int
    {
        $this->browser->request('POST', '/store-api/account/login', [
            'email' => $this->email,
            'password' => 'shopware',
        ]);

        $token = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        if (\is_string($token)) {
            $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $token);
        }

        return $this->browser->getResponse()->getStatusCode();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function addProductToCart(): array
    {
        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'items' => [[
                    'id' => $this->ids->get('product'),
                    'referencedId' => $this->ids->get('product'),
                    'quantity' => 1,
                    'type' => 'product',
                ]],
            ], \JSON_THROW_ON_ERROR)
        );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        return $this->decodeResponse()['errors'] ?? [];
    }

    private function assertOrderIsRefused(): void
    {
        $before = $this->countOrders();

        $this->browser->request('POST', '/store-api/checkout/order', []);

        static::assertNotSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame($before, $this->countOrders(), 'no order may be persisted without a valid address');
    }

    private function countOrders(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `order`');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(): array
    {
        return json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function deleteAddress(string $key): void
    {
        $affected = $this->connection->delete('customer_address', ['id' => Uuid::fromHexToBytes($this->ids->get($key))]);

        static::assertSame(1, $affected);
    }

    private function createCustomer(): void
    {
        $repository = static::getContainer()->get('customer.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        $address = [
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'street' => 'Musterstraße 1',
            'city' => 'Schöppingen',
            'zipcode' => '12345',
            'salutationId' => $this->getValidSalutationId(),
            'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
        ];

        $repository->create([[
            'id' => $this->ids->create('customer'),
            'salesChannelId' => $this->ids->get('sales-channel'),
            'defaultBillingAddress' => ['id' => $this->ids->create('billing-address')] + $address,
            'defaultShippingAddress' => ['id' => $this->ids->create('shipping-address')] + $address,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $this->email,
            'password' => 'shopware',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
        ]], Context::createDefaultContext());
    }

    private function createProduct(string $type): void
    {
        $repository = static::getContainer()->get('product.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        $repository->create([[
            'id' => $this->ids->create('product'),
            'productNumber' => Uuid::randomHex(),
            'stock' => 100,
            'name' => 'Test product',
            'type' => $type,
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => 25,
                'net' => 25,
                'linked' => false,
            ]],
            'manufacturer' => ['name' => 'Test manufacturer'],
            'tax' => ['taxRate' => 19, 'name' => 'Test tax'],
            'active' => true,
            'visibilities' => [[
                'salesChannelId' => $this->ids->get('sales-channel'),
                'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
            ]],
        ]], Context::createDefaultContext());
    }
}
