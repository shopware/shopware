<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerEmailUniqueSubscriberTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    private Context $context;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->customerRepository = static::getContainer()->get('customer.repository');
        $this->context = Context::createDefaultContext();
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
    }

    protected function tearDown(): void
    {
        $this->systemConfigService->delete('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');

        parent::tearDown();
    }

    public function testRepositoryCreateRejectsDuplicateEmailWhenCustomersAreNotBoundToSalesChannel(): void
    {
        $this->setCustomerBoundToSalesChannel(false);

        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);

        $this->expectCustomerEmailViolation(fn () => $this->createCustomer($email));
    }

    public function testAdminApiCreateRejectsDuplicateEmailWhenCustomersAreNotBoundToSalesChannel(): void
    {
        $this->setCustomerBoundToSalesChannel(false);

        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);

        $this->getBrowser()->jsonRequest('POST', '/api/customer', $this->createCustomerPayload($email));

        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame(CustomerEmailUnique::CUSTOMER_EMAIL_NOT_UNIQUE, $content['errors'][0]['code']);
    }

    public function testRepositoryCreateAllowsDuplicateEmailForDifferentBoundSalesChannelsWhenCustomersAreBoundToSalesChannel(): void
    {
        $this->setCustomerBoundToSalesChannel(true);

        $email = Uuid::randomHex() . '@example.com';
        $salesChannel = $this->createSalesChannel([
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost2',
                ],
            ],
        ]);

        $this->createCustomer($email, ['boundSalesChannelId' => TestDefaults::SALES_CHANNEL]);
        $this->createCustomer($email, [
            'salesChannelId' => $salesChannel['id'],
            'boundSalesChannelId' => $salesChannel['id'],
        ]);

        static::addToAssertionCount(1);
    }

    public function testRepositoryCreateRejectsDuplicateEmailForSameBoundSalesChannelWhenCustomersAreBoundToSalesChannel(): void
    {
        $this->setCustomerBoundToSalesChannel(true);

        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email, ['boundSalesChannelId' => TestDefaults::SALES_CHANNEL]);

        $this->expectCustomerEmailViolation(fn () => $this->createCustomer($email, ['boundSalesChannelId' => TestDefaults::SALES_CHANNEL]));
    }

    public function testRepositoryCreateAllowsDuplicateGuestEmail(): void
    {
        $this->setCustomerBoundToSalesChannel(false);

        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);
        $this->createCustomer($email, ['guest' => true]);

        static::addToAssertionCount(1);
    }

    public function testRepositoryUpdateRejectsDuplicateEmail(): void
    {
        $this->setCustomerBoundToSalesChannel(false);

        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);
        $customerId = $this->createCustomer(Uuid::randomHex() . '@example.com');

        $this->expectCustomerEmailViolation(fn () => $this->customerRepository->update([[
            'id' => $customerId,
            'email' => $email,
        ]], $this->context));
    }

    public function testRepositoryCreateRejectsDuplicateEmailInSameWriteBatch(): void
    {
        $this->setCustomerBoundToSalesChannel(false);

        $email = Uuid::randomHex() . '@example.com';

        $this->expectCustomerEmailViolation(fn () => $this->customerRepository->create([
            $this->createCustomerPayload($email),
            $this->createCustomerPayload($email),
        ], $this->context));
    }

    public function testRepositoryUpdateAllowsEmailSwapInSameWriteBatch(): void
    {
        $this->setCustomerBoundToSalesChannel(false);

        $firstEmail = Uuid::randomHex() . '@example.com';
        $secondEmail = Uuid::randomHex() . '@example.com';
        $firstCustomerId = $this->createCustomer($firstEmail);
        $secondCustomerId = $this->createCustomer($secondEmail);

        $this->customerRepository->update([
            [
                'id' => $firstCustomerId,
                'email' => $secondEmail,
            ],
            [
                'id' => $secondCustomerId,
                'email' => $firstEmail,
            ],
        ], $this->context);

        static::addToAssertionCount(1);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createCustomer(string $email, array $overrides = []): string
    {
        $customer = $this->createCustomerPayload($email, $overrides);

        $this->customerRepository->create([$customer], $this->context);

        return $customer['id'];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function createCustomerPayload(string $email, array $overrides = []): array
    {
        $customerId = $overrides['id'] ?? Uuid::randomHex();
        $addressId = $overrides['defaultBillingAddressId'] ?? Uuid::randomHex();

        return array_replace_recursive([
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Ebbinghoff 10',
                'city' => 'Schöppingen',
                'zipcode' => '48624',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'guest' => false,
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => Uuid::randomHex(),
        ], $overrides);
    }

    private function expectCustomerEmailViolation(\Closure $callback): void
    {
        try {
            $callback();
            static::fail('Expected a customer email uniqueness violation.');
        } catch (WriteException $exception) {
            $writeException = $exception->getExceptions()[0] ?? null;

            static::assertInstanceOf(WriteConstraintViolationException::class, $writeException);
            static::assertSame(CustomerEmailUnique::CUSTOMER_EMAIL_NOT_UNIQUE, $writeException->getViolations()->get(0)->getCode());
        }
    }

    private function setCustomerBoundToSalesChannel(bool $value): void
    {
        $this->systemConfigService->set('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel', $value);
    }
}
