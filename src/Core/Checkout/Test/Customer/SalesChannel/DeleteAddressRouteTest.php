<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @group store-api
 */
#[Package('customer-order')]
class DeleteAddressRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);

        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer('shopware', $email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopware',
                ]
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    public function testDeleteNewCreatedAddress(): void
    {
        // Create
        $data = [
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Test',
            'lastName' => 'Test',
            'street' => 'Test',
            'city' => 'Test',
            'zipcode' => 'Test',
            'countryId' => $this->getValidCountryId(),
        ];

        $this->browser
            ->request(
                'POST',
                '/store-api/account/address',
                $data
            );

        $addressId = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['id'];

        // Check is listed
        $this->browser
            ->request(
                'POST',
                '/store-api/account/list-address',
                [
                ]
            );

        static::assertSame(2, json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['total']);

        // Delete
        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/address/' . $addressId
            );

        static::assertSame(204, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/list-address',
                [
                ]
            );

        static::assertSame(1, json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['total']);
    }

    public function testDeleteDefaultAddress(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/customer',
                []
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $billingAddressId = $response['defaultBillingAddressId'];
        $shippingAddressId = $response['defaultShippingAddressId'];

        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/address/' . $billingAddressId
            );

        static::assertNotSame(204, $this->browser->getResponse()->getStatusCode());
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('CHECKOUT__CUSTOMER_ADDRESS_IS_DEFAULT', $response['errors'][0]['code']);

        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/address/' . $shippingAddressId
            );

        static::assertNotSame(204, $this->browser->getResponse()->getStatusCode());
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('CHECKOUT__CUSTOMER_ADDRESS_IS_DEFAULT', $response['errors'][0]['code']);
    }

    /**
     * @group mysample
     */
    public function testDeleteActiveAddress(): void
    {
        $data = [
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Test',
            'lastName' => 'Test',
            'street' => 'Test',
            'city' => 'Test',
            'zipcode' => 'Test',
            'countryId' => $this->getValidCountryId(),
        ];

        $this->browser
            ->request(
                'POST',
                '/store-api/account/address',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) \json_encode($data, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $addressId = $response['id'];

        $contextData = [
            'billingAddressId' => $addressId,
            'shippingAddressId' => $addressId,
        ];

        $this->browser
        ->request(
            'PATCH',
            '/store-api/context',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) \json_encode($contextData, \JSON_THROW_ON_ERROR)
        );

        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/address/' . $addressId
            );

        static::assertNotSame(204, $this->browser->getResponse()->getStatusCode());
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('CHECKOUT__CUSTOMER_ADDRESS_IS_ACTIVE', $response['errors'][0]['code']);
    }

    public function testDeleteNewCreatedAddressGuest(): void
    {
        $customerId = $this->createCustomer(null, null, true);
        $context = $this->getLoggedInContextToken($customerId, $this->ids->get('sales-channel'));
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $context);

        // Create
        $data = [
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Test',
            'lastName' => 'Test',
            'street' => 'Test',
            'city' => 'Test',
            'zipcode' => 'Test',
            'countryId' => $this->getValidCountryId(),
        ];

        $this->browser
            ->request(
                'POST',
                '/store-api/account/address',
                $data
            );

        $addressId = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['id'];

        // Check is listed
        $this->browser
            ->request(
                'POST',
                '/store-api/account/list-address',
                [
                ]
            );

        static::assertSame(2, json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['total']);

        // Delete
        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/address/' . $addressId
            );

        static::assertSame(204, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/list-address',
                [
                ]
            );

        static::assertSame(1, json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['total']);
    }
}
