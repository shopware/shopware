<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @group store-api
 */
class ChangeEmailRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer('shopware', $email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopware',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $response['contextToken']);
    }

    public function testEmptyRequest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::CUSTOMER_PASSWORD_NOT_CORRECT', $response['errors'][0]['code']);
    }

    public function testChangeInvalidPassword(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'foooware',
                    'email' => 'test@fooware.de',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::CUSTOMER_PASSWORD_NOT_CORRECT', $response['errors'][0]['code']);
    }

    public function testChange(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'shopware',
                    'email' => 'test@fooware.de',
                    'emailConfirmation' => 'test@fooware.de',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayNotHasKey('errors', $response);
        static::assertTrue($response['success']);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/customer',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('test@fooware.de', $response['email']);
    }

    public function testChangeSuccessWithSameEmailOnDiffSalesChannel(): void
    {
        $this->getContainer()->get(SystemConfigService::class)->set('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel', true);

        $newEmail = 'test@fooware.de';

        $salesChannelContext = $this->createSalesChannelContext([
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost2',
                ],
            ],
        ]);

        $this->createCustomerOfSalesChannel($salesChannelContext->getSalesChannel()->getId(), $newEmail);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'shopware',
                    'email' => $newEmail,
                    'emailConfirmation' => $newEmail,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayNotHasKey('errors', $response);
        static::assertTrue($response['success']);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/customer',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame($newEmail, $response['email']);
    }

    public function testChangeFailWithSameEmailOnSameSalesChannel(): void
    {
        $newEmail = 'test@fooware.de';

        $this->createCustomerOfSalesChannel($this->ids->get('sales-channel'), $newEmail);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'shopware',
                    'email' => $newEmail,
                    'emailConfirmation' => $newEmail,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertEquals(400, $this->browser->getResponse()->getStatusCode());
        static::assertNotEmpty($response['errors']);
        static::assertEquals('VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE', $response['errors'][0]['code']);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/customer',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertNotEquals($newEmail, $response['email']);
    }

    private function createCustomer(string $password, ?string $email = null): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->customerRepository->create([
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schoöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'active' => true,
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'availabilityRule' => [
                        'id' => Uuid::randomHex(),
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'cartCartAmount',
                                'value' => [
                                    'operator' => '>=',
                                    'amount' => 0,
                                ],
                            ],
                        ],
                    ],
                    'salesChannels' => [
                        [
                            'id' => Defaults::SALES_CHANNEL,
                        ],
                    ],
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ], $this->ids->context);

        return $customerId;
    }

    private function createCustomerOfSalesChannel(string $salesChannelId, string $email): string
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

        $isCustomerBound = $this->getContainer()->get(SystemConfigService::class)->get('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');
        $customer['boundSalesChannelId'] = $isCustomerBound ? $salesChannelId : null;

        $this->getContainer()
            ->get('customer.repository')
            ->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }
}
