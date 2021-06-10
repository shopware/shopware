<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @group store-api
 */
class SwitchDefaultAddressRouteTest extends TestCase
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

    /**
     * @var string
     */
    private $newAddressId;

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

        $this->newAddressId = $this->createAddress();
    }

    public function testSwitchBilling(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/customer',
                []
            );

        $oldBillingId = json_decode($this->browser->getResponse()->getContent(), true)['defaultBillingAddressId'];
        static::assertNotSame($oldBillingId, $this->newAddressId);

        $this->browser
            ->request(
                'PATCH',
                '/store-api/account/address/default-billing/' . $this->newAddressId,
                []
            );

        $this->browser
            ->request(
                'POST',
                '/store-api/account/customer',
                []
            );

        $newBillingId = json_decode($this->browser->getResponse()->getContent(), true)['defaultBillingAddressId'];
        static::assertSame($newBillingId, $this->newAddressId);
    }

    public function testSwitchShipping(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/customer',
                []
            );

        $oldShippingId = json_decode($this->browser->getResponse()->getContent(), true)['defaultShippingAddressId'];
        static::assertNotSame($oldShippingId, $this->newAddressId);

        $this->browser
            ->request(
                'PATCH',
                '/store-api/account/address/default-shipping/' . $this->newAddressId,
                []
            );

        $this->browser
            ->request(
                'POST',
                '/store-api/account/customer',
                []
            );

        $newShippingId = json_decode($this->browser->getResponse()->getContent(), true)['defaultShippingAddressId'];
        static::assertSame($newShippingId, $this->newAddressId);
    }

    private function createAddress(): string
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

        return json_decode($this->browser->getResponse()->getContent(), true)['id'];
    }
}
