<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;

/**
 * @group store-api
 */
class CustomerRouteTest extends TestCase
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

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('contextToken', $response);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $response['contextToken']);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/customer',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        list('id' => $id, 'email' => $email) = json_decode($registerResponse->getContent(), true);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/customer',
                [
                ]
            );

        $customerResponse = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame($id, $customerResponse['id']);
        static::assertSame($email, $customerResponse['email']);
    }

    private function getGuestRegistrationData(string $storefrontUrl = 'http://localhost'): array
    {
        return [
            'guest' => true,
            'salutationId' => $this->getValidSalutationId(),
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
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Test 2',
                'lastName' => 'Example 2',
                'street' => 'Examplestreet 111',
                'zipcode' => '12341',
                'city' => 'Berlin',
            ],
        ];
    }
}
