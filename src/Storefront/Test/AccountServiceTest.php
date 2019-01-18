<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\CustomerAccountExistsException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Checkout\Customer\Storefront\AccountService;
use Shopware\Core\Checkout\Exception\CustomerNotFoundException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class AccountServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var CheckoutContext
     */
    private $checkoutContext;

    protected function setUp()
    {
        $this->accountService = $this->getContainer()->get(AccountService::class);
        $checkoutContextFactory = $this->getContainer()->get(CheckoutContextFactory::class);

        $token = Uuid::uuid4()->getHex();
        $this->checkoutContext = $checkoutContextFactory->create($token, Defaults::SALES_CHANNEL);
    }

    public function testCreateCustomer(): void
    {
        $request = $this->getRegistrationRequest();

        $customerId = $this->accountService->createNewCustomer($request, $this->checkoutContext);
        static::assertNotEmpty($customerId);

        $customer = $this->accountService->getCustomerByEmail($request->requirePost('email'), $this->checkoutContext);
        static::assertEquals($request->requirePost('lastName'), $customer->getLastName());
        static::assertNotEquals($request->requirePost('password'), $customer->getPassword());
    }

    public function testCreateWithExistingCustomer(): void
    {
        $request = $this->getRegistrationRequest();

        $customerId = $this->accountService->createNewCustomer($request, $this->checkoutContext);
        static::assertNotEmpty($customerId);

        $this->expectException(CustomerAccountExistsException::class);
        $this->accountService->createNewCustomer($request, $this->checkoutContext);
    }

    public function testCreateGuestWithExistingCustomer(): void
    {
        $request = $this->getRegistrationRequest();

        $customerId = $this->accountService->createNewCustomer($request, $this->checkoutContext);
        static::assertNotEmpty($customerId);

        $request->addParam('guest', true);
        $customerId = $this->accountService->createNewCustomer($request, $this->checkoutContext);
        static::assertNotEmpty($customerId);

        $customers = $this->accountService->getCustomersByEmail($request->requirePost('email'), $this->checkoutContext);
        static::assertCount(2, $customers);

        $customers = $this->accountService->getCustomersByEmail($request->requirePost('email'), $this->checkoutContext, false);
        static::assertCount(1, $customers);

        $this->expectException(CustomerNotFoundException::class);
        $this->accountService->getCustomerByEmail($request->requirePost('email'), $this->checkoutContext, true);
    }

    public function testLoginWithAdditionalGuestAccount(): void
    {
        $request = $this->getRegistrationRequest();
        $request->addParam('guest', true);
        $customerId = $this->accountService->createNewCustomer($request, $this->checkoutContext);
        static::assertNotEmpty($customerId);

        $request->addParam('guest', false);
        $customerId = $this->accountService->createNewCustomer($request, $this->checkoutContext);
        static::assertNotEmpty($customerId);

        $customer = $this->accountService->getCustomerByEmail($request->requirePost('email'), $this->checkoutContext);
        static::assertEquals($request->requirePost('lastName'), $customer->getLastName());
    }

    private function getRegistrationRequest(): InternalRequest
    {
        $data = [
            'email' => 'max.mustermann@example.com',
            'salutation' => 'Herr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',

            'billingAddress.country' => Defaults::COUNTRY,
            'billingAddress.street' => 'Musterstrasse 13',
            'billingAddress.zipcode' => '48599',
            'billingAddress.city' => 'Epe',

            'password' => Uuid::uuid4()->getHex(),
        ];

        return new InternalRequest([], $data);
    }
}
