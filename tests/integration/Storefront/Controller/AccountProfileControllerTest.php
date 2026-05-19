<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @internal
 */
#[Package('checkout')]
class AccountProfileControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testDeleteCustomerProfile(): void
    {
        $context = Context::createDefaultContext();
        $customer = $this->createCustomer($context);

        $browser = $this->login($customer->getEmail());

        $browser->request('POST', $_SERVER['APP_URL'] . '/account/profile/delete');

        $response = $browser->getResponse();

        static::assertArrayHasKey('success', $this->getFlashBag()->all());
        static::assertTrue($response->isRedirect(), (string) $response->getContent());
    }

    public function testAccountOverviewPageLoadedScriptsAreExecuted(): void
    {
        $context = Context::createDefaultContext();
        $customer = $this->createCustomer($context);

        $browser = $this->login($customer->getEmail());

        $browser->request('GET', '/account');
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('account-overview-page-loaded', $traces);
    }

    public function testAccountProfilePageLoadedScriptsAreExecuted(): void
    {
        $context = Context::createDefaultContext();
        $customer = $this->createCustomer($context);

        $browser = $this->login($customer->getEmail());

        $browser->request('GET', '/account/profile');
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('account-profile-page-loaded', $traces);
    }

    public function testPrivateAccountTypeNotForcedToCommercialWithCompanySignupEnabled(): void
    {
        $context = Context::createDefaultContext();

        // Create a customer with private account type
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $customerGroupId = Uuid::randomHex();

        // Create a customer group with company signup form enabled
        $customerGroupRepository = static::getContainer()->get('customer_group.repository');
        $customerGroupRepository->create([
            [
                'id' => $customerGroupId,
                'name' => 'Test Group with Company Signup',
                'registrationActive' => true,
                'registrationTitle' => 'Company Registration',
                'registrationOnlyCompanyRegistration' => true, // This enables the company signup form
            ],
        ], $context);

        // Create a private customer
        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE, // Explicitly set as private
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'John',
                'lastName' => 'Doe',
                'street' => 'Test Street 1',
                'city' => 'Test City',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'country' => ['name' => 'Germany'],
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => $customerGroupId, // Assign to the group with company signup enabled
            'email' => 'private.customer@test.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '54321',
        ];

        /** @var EntityRepository<CustomerCollection> $repo */
        $repo = static::getContainer()->get('customer.repository');
        $repo->create([$customer], $context);

        // Enable the account type selection in system config
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.showAccountTypeSelection', true);

        // Login as the private customer
        $browser = $this->login($customer['email']);

        // Request the profile page
        $browser->request('GET', '/account/profile');
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Check that the page content shows the correct account type
        $content = $response->getContent();
        static::assertNotFalse($content);

        // The account type field should be present and not forcibly set to commercial
        static::assertStringContainsString('name="accountType"', $content);

        // Check that private account option is available and selected
        static::assertStringContainsString('value="' . CustomerEntity::ACCOUNT_TYPE_PRIVATE . '"', $content);

        // Verify that the profile can be saved without requiring company fields
        $browser->request(
            'POST',
            '/account/profile',
            $this->tokenize('frontend.account.profile.save', [
                'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'John',
                'lastName' => 'Doe',
                'email' => 'private.customer@test.com',
            ])
        );

        $response = $browser->getResponse();
        static::assertTrue($response->isRedirect(), 'Response should redirect after saving profile. Response code: ' . $response->getStatusCode());

        // Verify the customer is still private type in the database
        $updatedCustomer = $repo->search(new Criteria([$customerId]), $context)->getEntities()->first();
        static::assertNotNull($updatedCustomer);
        static::assertSame(CustomerEntity::ACCOUNT_TYPE_PRIVATE, $updatedCustomer->getAccountType());
    }

    private function login(string $email): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $email,
                'password' => 'shopware',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $browser;
    }

    private function createCustomer(Context $context): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
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
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'test@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
        ];

        /** @var EntityRepository<CustomerCollection> $repo */
        $repo = static::getContainer()->get('customer.repository');

        $repo->create([$customer], $context);

        $customer = $repo->search(new Criteria([$customerId]), $context)->getEntities()->first();

        static::assertNotNull($customer);

        return $customer;
    }

    private function getFlashBag(): FlashBagInterface
    {
        $session = $this->getSession();

        static::assertInstanceOf(Session::class, $session);

        return $session->getFlashBag();
    }
}
