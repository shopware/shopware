<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountRegistrationService;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

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
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    protected function setUp(): void
    {
        $this->accountRegistrationService = $this->getContainer()->get(AccountRegistrationService::class);
        $this->accountService = $this->getContainer()->get(AccountService::class);
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        $token = Uuid::randomHex();
        $this->salesChannelContext = $salesChannelContextFactory->create($token, Defaults::SALES_CHANNEL);
    }

    public function testCreateCustomer(): void
    {
        $data = $this->getRegistrationData();

        $customerId = $this->accountRegistrationService->register($data, false, $this->salesChannelContext);
        static::assertNotEmpty($customerId);

        $customer = $this->accountService->getCustomerByEmail($data->get('email'), $this->salesChannelContext);
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

        $customers = $this->accountService->getCustomersByEmail($data->get('email'), $this->salesChannelContext);
        static::assertCount(2, $customers);

        $customers = $this->accountService->getCustomersByEmail($data->get('email'), $this->salesChannelContext, false);
        static::assertCount(1, $customers);

        /** @var CustomerEntity $customer */
        $customer = $this->accountService->getCustomerByEmail($data->get('email'), $this->salesChannelContext, true);
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

        $customer = $this->accountService->getCustomerByEmail($data->get('email'), $this->salesChannelContext);
        static::assertEquals($data->get('lastName'), $customer->getLastName());
    }

    private function getRegistrationData($isGuest = false): DataBag
    {
        $data = [
            'email' => 'max.mustermann@example.com',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',

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
}
