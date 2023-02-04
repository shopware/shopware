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
class CustomerRouteTest extends TestCase
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
    }

    public function testNotLoggedin(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/account/customer',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_NOT_LOGGED_IN', $response['errors'][0]['code']);
    }

    public function testValid(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $id = $this->createCustomer($password, $email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/customer',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($id, $response['id']);
    }

    public function testValidGuest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                $this->getGuestRegistrationData()
            );

        $registerResponse = $this->browser->getResponse();
        static::assertTrue($registerResponse->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        $contextToken = $registerResponse->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertNotEmpty($contextToken);

        ['id' => $id, 'email' => $email] = json_decode((string) $registerResponse->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/customer',
                [
                ]
            );

        $customerResponse = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($id, $customerResponse['id']);
        static::assertSame($email, $customerResponse['email']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getGuestRegistrationData(string $storefrontUrl = 'http://localhost'): array
    {
        return [
            'guest' => true,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'teg-reg@example.com',
            'storefrontUrl' => $storefrontUrl,
            'billingAddress' => [
                'countryId' => $this->getValidCountryId(),
                'street' => 'Examplestreet 11',
                'zipcode' => '48441',
                'city' => 'Cologne',
            ],
            'shippingAddress' => [
                'countryId' => $this->getValidCountryId(),
                'firstName' => 'Test 2',
                'lastName' => 'Example 2',
                'street' => 'Examplestreet 111',
                'zipcode' => '12341',
                'city' => 'Berlin',
            ],
        ];
    }
}
