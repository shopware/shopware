<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @group store-api
 */
class DeleteAddressRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    private EntityRepositoryInterface $customerRepository;

    private EntityRepositoryInterface $addressRepository;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->addressRepository = $this->getContainer()->get('customer_address.repository');

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $response['contextToken']);
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

        $addressId = json_decode($this->browser->getResponse()->getContent(), true)['id'];

        // Check is listed
        $this->browser
            ->request(
                'POST',
                '/store-api/account/list-address',
                [
                ]
            );

        static::assertSame(2, json_decode($this->browser->getResponse()->getContent(), true)['total']);

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

        static::assertSame(1, json_decode($this->browser->getResponse()->getContent(), true)['total']);
    }

    public function testDeleteDefaultAddress(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/customer',
                []
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        $billingAddressId = $response['defaultBillingAddressId'];
        $shippingAddressId = $response['defaultShippingAddressId'];

        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/address/' . $billingAddressId
            );

        static::assertNotSame(204, $this->browser->getResponse()->getStatusCode());
        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('CHECKOUT__CUSTOMER_ADDRESS_IS_DEFAULT', $response['errors'][0]['code']);

        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/address/' . $shippingAddressId
            );

        static::assertNotSame(204, $this->browser->getResponse()->getStatusCode());
        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('CHECKOUT__CUSTOMER_ADDRESS_IS_DEFAULT', $response['errors'][0]['code']);
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

        $addressId = json_decode($this->browser->getResponse()->getContent(), true)['id'];

        // Check is listed
        $this->browser
            ->request(
                'POST',
                '/store-api/account/list-address',
                [
                ]
            );

        static::assertSame(2, json_decode($this->browser->getResponse()->getContent(), true)['total']);

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

        static::assertSame(1, json_decode($this->browser->getResponse()->getContent(), true)['total']);
    }
}
