<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;

class UpsertAddressRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $addressRepository;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->addressRepository = $this->getContainer()->get('customer_address.repository');
    }

    public function testCreateAddress(): void
    {
        $this->loginAccount();
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
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/address',
                $data
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('id', $response);

        foreach ($data as $key => $val) {
            static::assertSame($val, $response[$key]);
        }

        // Check existence
        /** @var CustomerAddressEntity $address */
        $address = $this->addressRepository->search(new Criteria([$response['id']]), $this->ids->getContext())->first();

        foreach ($data as $key => $val) {
            static::assertSame($val, $address->jsonSerialize()[$key]);
        }
    }

    public function testRequestWithNoParameters(): void
    {
        $this->loginAccount();
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/address',
                []
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $response);
        static::assertCount(7, $response['errors']);
    }

    public function testUpdateExistingAddress(): void
    {
        $this->loginAccount();
        // Fetch address
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/customer',
                []
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        $addressId = $response['defaultBillingAddressId'];

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/list-address',
                [
                ]
            );

        $address = json_decode($this->browser->getResponse()->getContent(), true)['elements'][0];
        $address['firstName'] = __FUNCTION__;

        // Update
        $this->browser
            ->request(
                'PATCH',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/address/' . $addressId,
                $address
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        // Verify

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/list-address',
                [
                ]
            );

        $updatedAddress = json_decode($this->browser->getResponse()->getContent(), true)['elements'][0];
        unset($address['updatedAt'], $updatedAddress['updatedAt']);

        static::assertSame($address, $updatedAddress);
    }

    public function testUpdateExistingAddressOfGuestAccount(): void
    {
        $this->registerAccount();

        // Fetch customer from context
        $this->browser
            ->request(
                'GET',
                '/store-api/v' . PlatformRequest::API_VERSION . '/context',
                []
            );

        list('customer' => $customer) = json_decode($this->browser->getResponse()->getContent(), true);
        list('defaultBillingAddressId' => $addressId, 'defaultBillingAddress' => $address) = $customer;
        $address['firstName'] = __FUNCTION__;

        // Update
        $this->browser
            ->request(
                'PATCH',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/address/' . $addressId,
                $address
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        // Verify

        $this->browser
            ->request(
                'GET',
                '/store-api/v' . PlatformRequest::API_VERSION . '/context',
                []
            );

        list('customer' => $customer) = json_decode($this->browser->getResponse()->getContent(), true);
        unset($address['updatedAt'], $customer['defaultBillingAddress']['updatedAt']);

        static::assertSame($address, $customer['defaultBillingAddress']);
    }

    private function loginAccount()
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer('shopware', $email);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                [
                    'email' => $email,
                    'password' => 'shopware',
                ]
            );

        list('contextToken' => $contextToken) = json_decode($this->browser->getResponse()->getContent(), true);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    private function registerAccount()
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/register',
                [
                    'guest' => true,
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'email' => Uuid::randomHex() . '@example.com',
                    'storefrontUrl' => 'http://localhost',
                    'billingAddress' => [
                        'countryId' => $this->getValidCountryId(),
                        'street' => 'Examplestreet 11',
                        'zipcode' => '48441',
                        'city' => 'Cologne',
                    ],
                    'shippingAddress' => [
                        'countryId' => $this->getValidCountryId(),
                        'salutationId' => $this->getValidSalutationId(),
                        'firstName' => 'Test 2',
                        'lastName' => 'Example 2',
                        'street' => 'Examplestreet 111',
                        'zipcode' => '12341',
                        'city' => 'Berlin',
                    ],
                ]
            );

        $contextToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }
}
