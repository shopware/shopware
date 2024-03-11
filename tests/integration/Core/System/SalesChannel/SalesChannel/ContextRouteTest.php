<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextRoute;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(ContextRoute::class)]
#[Group('store-api')]
class ContextRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    public function testFetchingContext(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/context'
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('salesChannel', $response);
        static::assertSame($response['salesChannel']['id'], $this->ids->get('sales-channel'));
    }

    public function testFetchingContextWithCustomer(): void
    {
        $this->login($this->browser);

        $this->browser
            ->request(
                'GET',
                '/store-api/context'
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('salesChannel', $response);
        static::assertSame($response['salesChannel']['id'], $this->ids->get('sales-channel'));

        static::assertArrayHasKey('customer', $response);
        static::assertArrayHasKey('activeBillingAddress', $response['customer']);
        static::assertArrayHasKey('activeShippingAddress', $response['customer']);
    }

    public function testFetchingContextAfterBillingAddressChange(): void
    {
        $customerId = $this->login($this->browser);

        $this->browser
            ->request(
                'GET',
                '/store-api/context'
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('customer', $response);
        static::assertArrayHasKey('activeBillingAddress', $response['customer']);

        $newBillingAddressId = Uuid::randomHex();
        $addressRepository = $this->getContainer()->get('customer_address.repository');
        $addressRepository->create([
            [
                'id' => $newBillingAddressId,
                'customerId' => $customerId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
        ], Context::createDefaultContext());

        $this->browser
            ->request(
                'PATCH',
                '/store-api/context',
                [
                    'billingAddressId' => $newBillingAddressId,
                ]
            );

        $this->browser
            ->request(
                'GET',
                '/store-api/context'
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('customer', $response);
        static::assertArrayHasKey('activeBillingAddress', $response['customer']);
        static::assertArrayHasKey('id', $response['customer']['activeBillingAddress']);
        static::assertSame($newBillingAddressId, $response['customer']['activeBillingAddress']['id']);
    }
}
