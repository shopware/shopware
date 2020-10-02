<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AuthControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testSessionIsInvalidatedOnLogOut(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', true);

        $browser = $this->login();

        $session = $browser->getRequest()->getSession();
        $contextToken = $session->get('sw-context-token');

        $sessionId = $session->getId();

        $browser->request('GET', '/account/logout', []);
        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent());

        $browser->request('GET', '/', []);
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $session = $browser->getRequest()->getSession();

        $newContextToken = $session->get('sw-context-token');
        static::assertNotEquals($contextToken, $newContextToken);

        $newSessionId = $session->getId();
        static::assertNotEquals($sessionId, $newSessionId);

        $oldCartExists = $connection->fetchColumn('SELECT 1 FROM cart WHERE token = ?', [$contextToken]);
        static::assertFalse($oldCartExists);

        $oldContextExists = $connection->fetchColumn('SELECT 1 FROM sales_channel_api_context WHERE token = ?', [$contextToken]);
        static::assertFalse($oldContextExists);
    }

    public function testSessionIsInvalidatedOnLogOutIsDeactivated(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $browser = $this->login();

        $session = $browser->getRequest()->getSession();
        $contextToken = $session->get('sw-context-token');
        $sessionId = $session->getId();

        $browser->request('GET', '/account/logout', []);
        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent());

        $browser->request('GET', '/', []);
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $session = $browser->getRequest()->getSession();

        Feature::skipTestIfActive('FEATURE_NEXT_10058', $this);

        $newContextToken = $session->get('sw-context-token');
        static::assertSame($contextToken, $newContextToken);

        $newSessionId = $session->getId();
        static::assertSame($sessionId, $newSessionId);
    }

    public function testRedirectToAccountPageAfterLogin(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/login', []);
        $response = $browser->getResponse();

        static::assertSame(302, $response->getStatusCode(), $response->getContent());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/account', $response->getTargetUrl());
    }

    public function testSessionIsMigratedOnLogOut(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10058', $this);
        $browser = $this->login();

        $session = $browser->getRequest()->getSession();
        $contextToken = $session->get('sw-context-token');
        $sessionId = $session->getId();

        $browser->request('GET', '/account/logout', []);
        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent());

        $browser->request('GET', '/', []);
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $session = $browser->getRequest()->getSession();

        $newContextToken = $session->get('sw-context-token');
        static::assertNotEquals($contextToken, $newContextToken);

        $newSessionId = $session->getId();
        static::assertNotEquals($sessionId, $newSessionId);
    }

    public function testOneUserUseOneContextAcrossSessions(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10058', $this);

        $browser = $this->login();

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $firstTimeLogin = $browser->getRequest()->getSession();
        $firstTimeLoginSessionId = $firstTimeLogin->getId();
        $firstTimeLoginContextToken = $firstTimeLogin->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        $browser->request('GET', '/account/logout', []);

        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent());

        $browser->request('GET', '/', []);
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => 'test@example.com',
                'password' => 'test',
            ])
        );

        $secondTimeLogin = $browser->getRequest()->getSession();
        $secondTimeLoginSessionId = $secondTimeLogin->getId();
        $secondTimeLoginContextToken = $secondTimeLogin->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        static::assertNotEquals($firstTimeLoginSessionId, $secondTimeLoginSessionId);
        static::assertEquals($firstTimeLoginContextToken, $secondTimeLoginContextToken);
    }

    private function login(): KernelBrowser
    {
        $customer = $this->createCustomer();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $customer->getEmail(),
                'password' => 'test',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $browser->request('GET', '/');
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();
        static::assertNotNull($response->getContext()->getCustomer());

        return $browser;
    }

    private function createCustomer(): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $data = [
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'country' => ['name' => 'Germany'],
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'test@example.com',
                'password' => 'test',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, Context::createDefaultContext());

        return $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
    }
}
