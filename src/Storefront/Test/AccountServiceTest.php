<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\CustomerAccountExistsException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Exception\CustomerNotFoundException;
use Shopware\Storefront\Page\Account\AccountService;
use Shopware\Storefront\Page\Account\RegistrationRequest;

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
        $this->checkoutContext = $checkoutContextFactory->create(Defaults::TENANT_ID, $token, Defaults::SALES_CHANNEL);
    }

    public function testCreateCustomer(): void
    {
        $request = $this->getRegistrationRequest();

        $customerId = $this->accountService->createNewCustomer($request, $this->checkoutContext);
        $this->assertNotEmpty($customerId);

        $customer = $this->accountService->getCustomerByEmail($request->getEmail(), $this->checkoutContext);
        $this->assertEquals($request->getLastName(), $customer->getLastName());
        $this->assertNotEquals($request->getPassword(), $customer->getPassword());
    }

    public function testCreateWithExistingCustomer(): void
    {
        $request = $this->getRegistrationRequest();

        $customerId = $this->accountService->createNewCustomer($request, $this->checkoutContext);
        $this->assertNotEmpty($customerId);

        $this->expectException(CustomerAccountExistsException::class);
        $this->accountService->createNewCustomer($request, $this->checkoutContext);
    }

    public function testCreateGuestWithExistingCustomer(): void
    {
        $request = $this->getRegistrationRequest();

        $customerId = $this->accountService->createNewCustomer($request, $this->checkoutContext);
        $this->assertNotEmpty($customerId);

        $request->setGuest(true);
        $customerId = $this->accountService->createNewCustomer($request, $this->checkoutContext);
        $this->assertNotEmpty($customerId);

        $customers = $this->accountService->getCustomersByEmail($request->getEmail(), $this->checkoutContext, true);
        $this->assertCount(2, $customers);

        $customers = $this->accountService->getCustomersByEmail($request->getEmail(), $this->checkoutContext, false);
        $this->assertCount(1, $customers);

        $this->expectException(CustomerNotFoundException::class);
        $this->accountService->getCustomerByEmail($request->getEmail(), $this->checkoutContext, true);
    }

    public function testLoginWithAdditionalGuestAccount()
    {
        $request = $this->getRegistrationRequest();
        $request->setGuest(true);
        $customerId = $this->accountService->createNewCustomer($request, $this->checkoutContext);
        $this->assertNotEmpty($customerId);

        $request->setGuest(false);
        $customerId = $this->accountService->createNewCustomer($request, $this->checkoutContext);
        $this->assertNotEmpty($customerId);

        $customer = $this->accountService->getCustomerByEmail($request->getEmail(), $this->checkoutContext);
        $this->assertEquals($request->getLastName(), $customer->getLastName());
    }

    private function getRegistrationRequest()
    {
        $data = [
            'email' => 'max.mustermann@example.com',
            'salutation' => 'Herr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',

            'billingCountry' => Defaults::COUNTRY,
            'billingStreet' => 'Musterstrasse 13',
            'billingZipcode' => '48599',
            'billingCity' => 'Epe',

            'password' => Uuid::uuid4()->getHex(),
        ];
        $request = new RegistrationRequest();
        $request->assign($data);

        return $request;
    }
}
