<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class AccountServiceTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    /**
     * @var AccountService
     */
    private $accountService;

    protected function setUp(): void
    {
        $this->accountService = $this->getContainer()->get(AccountService::class);
    }

    public function testGetCustomerByLogin(): void
    {
        $email = 'johndoe@example.com';

        $context = $this->createSalesChannelContext([
            'domains' => [
                [
                    'url' => 'http://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);
        $this->createCustomerOfSalesChannel($context->getSalesChannel()->getId(), $email);

        $customer = $this->accountService->getCustomerByLogin($email, 'shopware', $context);
        static::assertInstanceOf(CustomerEntity::class, $customer);
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
                    'url' => 'http://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);
        $this->createCustomerOfSalesChannel($context->getSalesChannel()->getId(), $email);

        $customer = $this->accountService->getCustomerByLogin($email, 'invalid-password', $context);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertEquals($email, $customer->getEmail());
        static::assertEquals($context->getSalesChannel()->getId(), $customer->getSalesChannelId());
    }

    public function testGetCustomerByLoginWhenCustomersHaveSameEmail(): void
    {
        $email = 'johndoe@example.com';

        $context1 = $this->createSalesChannelContext([
            'domains' => [
                [
                    'url' => 'http://test.de',
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

        static::assertInstanceOf(CustomerEntity::class, $customer1);
        static::assertEquals($context1->getSalesChannel()->getId(), $customer1->getSalesChannelId());

        $customer2 = $this->accountService->getCustomerByLogin($email, 'shopware', $context2);
        static::assertInstanceOf(CustomerEntity::class, $customer2);
        static::assertEquals($context2->getSalesChannel()->getId(), $customer2->getSalesChannelId());
    }

    private function createCustomerOfSalesChannel(string $salesChannelId, string $email, bool $boundToSalesChannel = true): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => $email,
            'password' => 'shopware',
            'boundSalesChannelId' => $boundToSalesChannel ? $salesChannelId : null,
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => $salesChannelId,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
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
