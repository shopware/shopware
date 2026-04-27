<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\ImitateCustomerTokenGenerator;
use Shopware\Core\Checkout\Customer\SalesChannel\ImitateCustomerRoute;
use Shopware\Core\Checkout\Customer\Struct\ImitateCustomerToken;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class ImitateCustomerRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    public function testHeadlessClientDetectsImitationViaImitatedFlag(): void
    {
        $customerEmail = Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomerForBrowser($customerEmail);
        $userId = $this->createAdminUser();

        $tokenGenerator = static::getContainer()->get(ImitateCustomerTokenGenerator::class);

        if (Feature::isActive('v6.8.0.0')) {
            $tokenStruct = new ImitateCustomerToken();
            $tokenStruct->salesChannelId = $this->ids->get('sales-channel');
            $tokenStruct->customerId = $customerId;
            $tokenStruct->iss = $userId;

            $imitateToken = $tokenGenerator->encode($tokenStruct);

            $payload = [ImitateCustomerRoute::TOKEN => $imitateToken];
        } else {
            $imitateToken = Feature::silent(
                'v6.8.0.0',
                fn (): string => $tokenGenerator->generate($this->ids->get('sales-channel'), $customerId, $userId)
            );

            $payload = [
                ImitateCustomerRoute::TOKEN => $imitateToken,
                ImitateCustomerRoute::CUSTOMER_ID => $customerId,
                ImitateCustomerRoute::USER_ID => $userId,
            ];
        }

        $this->browser->request(
            'POST',
            '/store-api/account/login/imitate-customer',
            $payload
        );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $imitatedContextToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $imitatedContextToken, 'imitate-customer login must return a sw-context-token header');

        // Drop any Symfony session cookie set during the imitation login so the next
        // call mimics a purely headless client carrying only `sw-context-token`.
        $this->browser->getCookieJar()->clear();
        $this->browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN, $imitatedContextToken);

        $this->browser->request('GET', '/store-api/context');
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $context = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('imitated', $context);
        static::assertTrue($context['imitated'], 'Headless context token must report `imitated: true` after imitation login');
        static::assertArrayHasKey('imitatingUserId', $context);
        static::assertNull($context['imitatingUserId'], 'imitatingUserId must remain masked over the Store API surface');

        $this->browser->request('POST', '/store-api/account/logout');
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $postLogoutToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $postLogoutToken, 'logout must return a sw-context-token header');

        $this->browser->getCookieJar()->clear();
        $this->browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN, $postLogoutToken);

        $this->browser->request('GET', '/store-api/context');
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $context = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($context['imitated'], 'After logout, headless context token must no longer report imitation');
    }

    public function testRegularCredentialsLoginAfterImitationClearsImitatedFlag(): void
    {
        $imitatedEmail = Uuid::randomHex() . '@example.com';
        $imitatedCustomerId = $this->createCustomerForBrowser($imitatedEmail);
        $userId = $this->createAdminUser();

        $regularLoginEmail = Uuid::randomHex() . '@example.com';
        $this->createCustomerForBrowser($regularLoginEmail);

        $tokenGenerator = static::getContainer()->get(ImitateCustomerTokenGenerator::class);

        if (Feature::isActive('v6.8.0.0')) {
            $tokenStruct = new ImitateCustomerToken();
            $tokenStruct->salesChannelId = $this->ids->get('sales-channel');
            $tokenStruct->customerId = $imitatedCustomerId;
            $tokenStruct->iss = $userId;

            $imitateToken = $tokenGenerator->encode($tokenStruct);
            $imitatePayload = [ImitateCustomerRoute::TOKEN => $imitateToken];
        } else {
            $imitateToken = Feature::silent(
                'v6.8.0.0',
                fn (): string => $tokenGenerator->generate($this->ids->get('sales-channel'), $imitatedCustomerId, $userId)
            );
            $imitatePayload = [
                ImitateCustomerRoute::TOKEN => $imitateToken,
                ImitateCustomerRoute::CUSTOMER_ID => $imitatedCustomerId,
                ImitateCustomerRoute::USER_ID => $userId,
            ];
        }

        $this->browser->request('POST', '/store-api/account/login/imitate-customer', $imitatePayload);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $imitatedContextToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $imitatedContextToken);

        // Headless: drop session cookies and reuse the imitation context token
        // to log in with a regular customer's credentials.
        $this->browser->getCookieJar()->clear();
        $this->browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN, $imitatedContextToken);

        $this->browser->request(
            'POST',
            '/store-api/account/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['email' => $regularLoginEmail, 'password' => 'shopware'])
        );
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $regularContextToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $regularContextToken);

        $this->browser->getCookieJar()->clear();
        $this->browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN, $regularContextToken);

        $this->browser->request('GET', '/store-api/context');
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $context = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse(
            $context['imitated'],
            'Regular credentials login on a context token previously used for imitation must clear the imitated flag'
        );
        static::assertSame($regularLoginEmail, $context['customer']['email']);
    }

    private function createCustomerForBrowser(string $email): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');
        $customerRepository->create([
            [
                'id' => $customerId,
                'salesChannelId' => $this->ids->get('sales-channel'),
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => 'cfbd5018d38d41d8adca10d94fc8bdd6',
                'email' => $email,
                'password' => 'shopware',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ], Context::createDefaultContext());

        return $customerId;
    }

    private function createAdminUser(): string
    {
        $userId = Uuid::randomHex();

        /** @var EntityRepository<UserCollection> $userRepository */
        $userRepository = static::getContainer()->get('user.repository');
        $userRepository->create([
            [
                'id' => $userId,
                'username' => 'imitator-' . Uuid::randomHex(),
                'firstName' => 'Imitator',
                'lastName' => 'Admin',
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'shopware-admin-pw',
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
            ],
        ], Context::createDefaultContext());

        return $userId;
    }
}
