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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @group store-api
 */
class UpsertAddressRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    private EntityRepositoryInterface $addressRepository;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
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

    public function testCreateAddress(): void
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
        $this->browser
            ->request(
                'POST',
                '/store-api/account/address',
                []
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $response);
        static::assertCount(7, $response['errors']);
    }

    public function testUpdateExistingAddress(): void
    {
        // Fetch address
        $this->browser
            ->request(
                'POST',
                '/store-api/account/customer',
                []
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        $addressId = $response['defaultBillingAddressId'];

        $this->browser
            ->request(
                'POST',
                '/store-api/account/list-address',
                [
                ]
            );

        $address = json_decode($this->browser->getResponse()->getContent(), true)['elements'][0];
        $address['firstName'] = __FUNCTION__;

        // Update
        $this->browser
            ->request(
                'PATCH',
                '/store-api/account/address/' . $addressId,
                $address
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        // Verify

        $this->browser
            ->request(
                'POST',
                '/store-api/account/list-address',
                [
                ]
            );

        $updatedAddress = json_decode($this->browser->getResponse()->getContent(), true)['elements'][0];
        unset($address['updatedAt'], $updatedAddress['updatedAt']);

        static::assertSame($address, $updatedAddress);
    }

    public function testCreateAddressForGuest(): void
    {
        $customerId = $this->createCustomer(null, null, true);
        $contextToken = $this->getLoggedInContextToken($customerId, $this->ids->get('sales-channel'));
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

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
}
