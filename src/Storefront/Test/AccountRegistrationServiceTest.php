<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountRegistrationService;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class AccountRegistrationServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var AccountRegistrationService
     */
    private $accountRegistrationService;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    protected function setUp(): void
    {
        $this->accountRegistrationService = $this->getContainer()->get(AccountRegistrationService::class);
        $this->accountService = $this->getContainer()->get(AccountService::class);
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        $token = Uuid::randomHex();
        $this->salesChannelContext = $salesChannelContextFactory->create($token, Defaults::SALES_CHANNEL);
    }

    public function testCreateCustomer(): void
    {
        $data = $this->getRegistrationData();

        $customerId = $this->accountRegistrationService->register($data, false, $this->salesChannelContext);
        static::assertNotEmpty($customerId);

        $customer = $this->getCustomerByEmail($data->get('email'), $this->salesChannelContext);
        static::assertEquals($data->get('lastName'), $customer->getLastName());
        static::assertNotEquals($data->get('password'), $customer->getPassword());
        static::assertNotEmpty($customer->getCustomerNumber());
    }

    public function testCreateWithExistingCustomer(): void
    {
        $data = $this->getRegistrationData();

        $customerId = $this->accountRegistrationService->register($data, false, $this->salesChannelContext);
        static::assertNotEmpty($customerId);

        $this->expectException(ConstraintViolationException::class);
        $this->accountRegistrationService->register($data, false, $this->salesChannelContext);
    }

    public function testCreateGuestWithExistingCustomer(): void
    {
        $data = $this->getRegistrationData();
        $guestData = $this->getRegistrationData(true);

        $customerId = $this->accountRegistrationService->register($data, false, $this->salesChannelContext);
        static::assertNotEmpty($customerId);

        $customerId = $this->accountRegistrationService->register($guestData, true, $this->salesChannelContext);
        static::assertNotEmpty($customerId);

        $customers = $this->getCustomersByEmail($data->get('email'), $this->salesChannelContext);
        static::assertCount(2, $customers);

        $customers = $this->getCustomersByEmail($data->get('email'), $this->salesChannelContext, false);
        static::assertCount(1, $customers);

        $customer = $this->getCustomerByEmail($data->get('email'), $this->salesChannelContext, true);
        static::assertTrue($customer->getGuest());
    }

    public function testLoginWithAdditionalGuestAccount(): void
    {
        $guestData = $this->getRegistrationData(true);
        $data = $this->getRegistrationData();

        $customerId = $this->accountRegistrationService->register($guestData, true, $this->salesChannelContext);
        static::assertNotEmpty($customerId);

        $customerId = $this->accountRegistrationService->register($data, false, $this->salesChannelContext);
        static::assertNotEmpty($customerId);

        $customer = $this->getCustomerByEmail($data->get('email'), $this->salesChannelContext);
        static::assertEquals($data->get('lastName'), $customer->getLastName());
    }

    public function testRegistrationWithBusinessAccount(): void
    {
        $this->systemConfigService->set('core.loginRegistration.showAccountTypeSelection', true);

        $guestData = $this->getRegistrationData();
        $guestData->set('accountType', CustomerEntity::ACCOUNT_TYPE_BUSINESS);

        $data = $this->getRegistrationData();
        $data->set('accountType', CustomerEntity::ACCOUNT_TYPE_BUSINESS);

        $this->expectException(ConstraintViolationException::class);
        $this->accountRegistrationService->register($guestData, true, $this->salesChannelContext);

        $this->expectException(ConstraintViolationException::class);
        $this->accountRegistrationService->register($data, true, $this->salesChannelContext);

        /** @var DataBag $guestBillingAddress */
        $guestBillingAddress = $guestData->get('billingAddress');
        $guestBillingAddress->set('company', 'shopware');
        /** @var DataBag $billingAddress */
        $billingAddress = $data->get('billingAddress');
        $billingAddress->set('company', 'shopware');

        $customerId = $this->accountRegistrationService->register($guestData, true, $this->salesChannelContext);
        static::assertNotEmpty($customerId);

        $customerId = $this->accountRegistrationService->register($data, true, $this->salesChannelContext);
        static::assertNotEmpty($customerId);
    }

    public function testRegistrationWithBusinessAccountAndVatIdRequired(): void
    {
        $this->systemConfigService->set('core.loginRegistration.showAccountTypeSelection', true);

        $guestData = $this->getRegistrationData();
        $guestData->set('accountType', CustomerEntity::ACCOUNT_TYPE_BUSINESS);

        /** @var DataBag $guestBillingAddress */
        $guestBillingAddress = $guestData->get('billingAddress');
        $guestBillingAddress->set('company', 'shopware');
        $guestData->set('vatIds', []);

        $this->systemConfigService->set('core.loginRegistration.vatIdFieldRequired', true);

        $this->expectException(ConstraintViolationException::class);
        $this->accountRegistrationService->register($guestData, true, $this->salesChannelContext);
    }

    public function testRegistrationWithRequiredPhoneNumber(): void
    {
        $this->systemConfigService->set('core.loginRegistration.showPhoneNumber', true);
        $this->systemConfigService->set('core.loginRegistration.requirePhoneNumber', true);

        $data = $this->getRegistrationData();

        $this->expectException(ConstraintViolationException::class);
        $this->accountRegistrationService->register($data, false, $this->salesChannelContext);

        /** @var DataBag $billingAddress */
        $billingAddress = $data->get('billingAddress');
        $billingAddress->set('phoneNumber', '12345');

        $customerId = $this->accountRegistrationService->register($data, false, $this->salesChannelContext);
        static::assertNotEmpty($customerId);
    }

    private function getRegistrationData(bool $isGuest = false): DataBag
    {
        $data = [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'email' => 'max.mustermann@example.com',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'storefrontUrl' => 'http://localhost',

            'billingAddress' => [
                'countryId' => $this->getValidCountryId(),
                'street' => 'Musterstrasse 13',
                'zipcode' => '48599',
                'city' => 'Epe',
            ],
        ];

        if (!$isGuest) {
            $data['password'] = Uuid::randomHex();
        }

        return new DataBag($data);
    }

    private function getCustomerByEmail(string $email, SalesChannelContext $context, bool $includeGuest = false): CustomerEntity
    {
        $customers = $this->getCustomersByEmail($email, $context, $includeGuest);

        $customerCount = $customers->count();
        if ($customerCount === 1) {
            return $customers->first();
        }

        if ($includeGuest && $customerCount) {
            $customers->sort(static function (CustomerEntity $a, CustomerEntity $b) {
                return $a->getCreatedAt() <=> $b->getCreatedAt();
            });

            return $customers->last();
        }

        throw new CustomerNotFoundException($email);
    }

    private function getCustomersByEmail(string $email, SalesChannelContext $context, bool $includeGuests = true): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer.email', $email));
        if (!$includeGuests) {
            $criteria->addFilter(new EqualsFilter('customer.guest', 0));
        }

        return $this->getContainer()
            ->get('customer.repository')
            ->search($criteria, $context->getContext());
    }
}
