<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Integration\Traits\CustomerTestTrait;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class SwitchDefaultAddressRouteTest extends TestCase
{
    use CustomerTestTrait;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private string $newAddressId;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);

        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);

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

        $oldBillingId = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['defaultBillingAddressId'];
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

        $newBillingId = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['defaultBillingAddressId'];
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

        $oldShippingId = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['defaultShippingAddressId'];
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

        $newShippingId = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['defaultShippingAddressId'];
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

        return json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['id'];
    }
}
