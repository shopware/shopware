<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @group store-api
 */
class ListAddressRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $response['contextToken']);
    }

    public function testListAddresses(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/list-address',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(1, $response['total']);
        static::assertNotEmpty($response['elements']);
        static::assertSame('Max', $response['elements'][0]['firstName']);
        static::assertSame('Mustermann', $response['elements'][0]['lastName']);
        static::assertSame('Musterstraße 1', $response['elements'][0]['street']);
        static::assertSame('Schöppingen', $response['elements'][0]['city']);
        static::assertSame('12345', $response['elements'][0]['zipcode']);
        static::assertSame($this->getValidCountryId(), $response['elements'][0]['countryId']);
    }

    public function testListAddressesIncludes(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/list-address',
                [
                    'includes' => [
                        'customer_address' => [
                            'firstName',
                        ],
                    ],
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(1, $response['total']);
        static::assertNotEmpty($response['elements']);
        static::assertSame([
            'firstName' => 'Max',
            'apiAlias' => 'customer_address',
        ], $response['elements'][0]);
    }

    public function testListAddressForGuest(): void
    {
        $contextToken = $this->getLoggedInContextToken($this->createCustomer(Random::getAlphanumericString(16), null, true), $this->ids->get('sales-channel'));

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/list-address',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(1, $response['total']);
        static::assertNotEmpty($response['elements']);
        static::assertSame('Max', $response['elements'][0]['firstName']);
        static::assertSame('Mustermann', $response['elements'][0]['lastName']);
        static::assertSame('Musterstraße 1', $response['elements'][0]['street']);
        static::assertSame('Schöppingen', $response['elements'][0]['city']);
        static::assertSame('12345', $response['elements'][0]['zipcode']);
        static::assertSame($this->getValidCountryId(), $response['elements'][0]['countryId']);
    }
}
