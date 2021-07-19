<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountNewsletterRecipientResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;

/**
 * @group store-api
 */
class AccountNewsletterRecipientRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $browser;

    private TestDataCollection $ids;

    private EntityRepositoryInterface $customerRepository;

    private EntityRepositoryInterface $newsletterRecipientRepository;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->newsletterRecipientRepository = $this->getContainer()->get('newsletter_recipient.repository');
    }

    public function testNotLoggedin(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/account/newsletter-recipient',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_NOT_LOGGED_IN', $response['errors'][0]['code']);
    }

    public function testValidNotSubscribed(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $this->createCustomer($password, $email);

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
                '/store-api/account/newsletter-recipient',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('account_newsletter_recipient', $response['apiAlias']);
        static::assertSame(AccountNewsletterRecipientResult::UNDEFINED, $response['status']);
    }

    public function testValidSubscribed(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $this->createCustomer($password, $email);

        $this->newsletterRecipientRepository->create(
            [
                [
                    'id' => Uuid::randomHex(),
                    'email' => $email,
                    'salesChannelId' => $this->ids->get('sales-channel'),
                    'status' => 'not-set',
                    'hash' => Uuid::randomHex(),
                ],
            ],
            $this->ids->context
        );

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
                '/store-api/account/newsletter-recipient'
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('not-set', $response['status']);
    }

    public function testGuestNotAllowed(): void
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

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/newsletter-recipient',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_NOT_LOGGED_IN', $response['errors'][0]['code']);
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
