<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('customer-order')]
class AccountServiceTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    private AccountService $accountService;

    protected function setUp(): void
    {
        $this->accountService = $this->getContainer()->get(AccountService::class);
    }

    public function testLogin(): void
    {
        $salesChannelContext = $this->createSalesChannelContext();
        $customerId = $this->createCustomerOfSalesChannel($salesChannelContext->getSalesChannelId(), 'foo@bar.com');
        $token = $this->accountService->login('foo@bar.com', $salesChannelContext);

        $customer = $this->getCustomerFromToken($token, $salesChannelContext->getSalesChannelId());

        static::assertSame('foo@bar.com', $customer->getEmail());
        static::assertSame($customerId, $customer->getId());
    }

    public function testLoginById(): void
    {
        $salesChannelContext = $this->createSalesChannelContext();
        $customerId = $this->createCustomerOfSalesChannel($salesChannelContext->getSalesChannelId(), 'foo@bar.com');
        $token = $this->accountService->loginById($customerId, $salesChannelContext);

        $customer = $this->getCustomerFromToken($token, $salesChannelContext->getSalesChannelId());

        static::assertSame('foo@bar.com', $customer->getEmail());
        static::assertSame($customerId, $customer->getId());
    }

    public function testGetCustomerByLogin(): void
    {
        $email = 'johndoe@example.com';

        $context = $this->createSalesChannelContext([
            'domains' => [
                [
                    'url' => 'https://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);
        $this->createCustomerOfSalesChannel($context->getSalesChannel()->getId(), $email);

        $customer = $this->accountService->getCustomerByLogin($email, 'shopware', $context);
        static::assertEquals($email, $customer->getEmail());
        static::assertEquals($context->getSalesChannel()->getId(), $customer->getSalesChannelId());
    }

    public function testGetCustomerByLoginWithInvalidPassword(): void
    {
        $this->expectException(BadCredentialsException::class);

        $email = 'johndoe@example.com';

        $context = $this->createSalesChannelContext([
            'domains' => [
                [
                    'url' => 'https://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);
        $this->createCustomerOfSalesChannel($context->getSalesChannel()->getId(), $email);

        $customer = $this->accountService->getCustomerByLogin($email, 'invalid-password', $context);
        static::assertEquals($email, $customer->getEmail());
        static::assertEquals($context->getSalesChannel()->getId(), $customer->getSalesChannelId());
    }

    public function testGetCustomerByLoginWhenCustomersHaveSameEmailReturnsTheLatestCreatedCustomer(): void
    {
        $idCustomer1 = Uuid::randomHex();
        $idCustomer2 = Uuid::randomHex();
        $email = 'johndoe@example.com';
        $context = $this->createSalesChannelContext([
            'domains' => [
                [
                    'url' => 'https://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);

        $this->createCustomerOfSalesChannel($context->getSalesChannel()->getId(), $email, true, true, $idCustomer1, '2022-10-21 10:00:00');
        $this->createCustomerOfSalesChannel($context->getSalesChannel()->getId(), $email, true, true, $idCustomer2, '2022-10-22 10:00:00');

        $customer = $this->accountService->getCustomerByLogin($email, 'shopware', $context);
        static::assertEquals($idCustomer2, $customer->getId());
    }

    public function testGetCustomerByLoginWhenCustomersInDifferentSalesChannelsHaveSameEmail(): void
    {
        $email = 'johndoe@example.com';

        $context1 = $this->createSalesChannelContext([
            'domains' => [
                [
                    'url' => 'https://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);
        $this->createCustomerOfSalesChannel($context1->getSalesChannel()->getId(), $email);

        $context2 = $this->createSalesChannelContext([
            'domains' => [
                [
                    'url' => 'http://test.en',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);

        $this->createCustomerOfSalesChannel($context2->getSalesChannel()->getId(), $email);

        $customer1 = $this->accountService->getCustomerByLogin($email, 'shopware', $context1);

        static::assertEquals($context1->getSalesChannel()->getId(), $customer1->getSalesChannelId());

        $customer2 = $this->accountService->getCustomerByLogin($email, 'shopware', $context2);
        static::assertEquals($context2->getSalesChannel()->getId(), $customer2->getSalesChannelId());
    }

    public function testCustomerFailsToLoginByMailWithInactiveAccount(): void
    {
        $email = 'johndoe@example.com';

        $context = $this->createSalesChannelContext([
            'domains' => [
                [
                    'url' => 'https://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);
        $this->createCustomerOfSalesChannel($context->getSalesChannel()->getId(), $email, true, false);

        $this->expectException(CustomerNotFoundException::class);
        $this->expectExceptionMessage('No matching customer for the email "johndoe@example.com" was found.');
        $this->accountService->getCustomerByLogin($email, 'shopware', $context);
    }

    private function getCustomerFromToken(string $contextToken, string $salesChannelId): CustomerEntity
    {
        $salesChannelContextService = $this->getContainer()->get(SalesChannelContextService::class);
        $context = $salesChannelContextService->get(
            new SalesChannelContextServiceParameters($salesChannelId, $contextToken)
        );

        $customer = $context->getCustomer();
        static::assertNotNull($customer);

        return $customer;
    }

    private function createCustomerOfSalesChannel(
        string $salesChannelId,
        string $email,
        bool $boundToSalesChannel = true,
        bool $active = true,
        ?string $customerId = null,
        ?string $createdAt = null,
    ): string {
        $customerId ??= Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'createdAt' => $createdAt,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => $email,
            'password' => 'shopware',
            'boundSalesChannelId' => $boundToSalesChannel ? $salesChannelId : null,
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => $salesChannelId,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'active' => $active,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->getContainer()
            ->get('customer.repository')
            ->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }
}
