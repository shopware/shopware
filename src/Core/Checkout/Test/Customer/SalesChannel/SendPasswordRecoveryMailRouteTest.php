<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Customer\Event\PasswordRecoveryUrlEvent;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @group store-api
 */
class SendPasswordRecoveryMailRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

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
    }

    public function testResetUnknownEmail(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password',
                [
                    'email' => 'lol@lol.de',
                    'storefrontUrl' => 'http://localhost',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_NOT_FOUND', $response['errors'][0]['code']);
    }

    public function testResetWithInvalidUrl(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password',
                [
                    'email' => 'lol@lol.de',
                    'storefrontUrl' => 'http://aaaa.de',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::NO_SUCH_CHOICE_ERROR', $response['errors'][0]['code']);
    }

    public function testResetWithTryingToDisableValidation(): void
    {
        $this->createCustomer('shopware1234', 'foo-test@test.de');

        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password?validateStorefrontUrl=false',
                [
                    'email' => 'foo-test@test.de',
                    'storefrontUrl' => 'http://my-evil-page',
                    'validateStorefrontUrl' => false,
                ]
            );

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('VIOLATION::NO_SUCH_CHOICE_ERROR', $response['errors'][0]['code']);
    }

    public function testResetWithDisabledAccount(): void
    {
        $email = 'test-disabled@test.de';

        $this->createCustomer('shopware1234', $email, false);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password?validateStorefrontUrl=false',
                [
                    'email' => $email,
                    'storefrontUrl' => 'http://localhost',
                    'validateStorefrontUrl' => false,
                ]
            );

        static::assertSame(404, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('CHECKOUT__CUSTOMER_NOT_FOUND', $response['errors'][0]['code']);
    }

    /**
     * @dataProvider sendMailWithDomainAndLeadingSlashProvider
     */
    public function testSendMailWithDomainAndLeadingSlash(array $domainUrlTest): void
    {
        $this->createCustomer('shopware1234', 'foo-test@test.de');

        $this->addDomain($domainUrlTest['domain']);

        $caughtEvent = null;
        $this->getContainer()->get('event_dispatcher')->addListener(
            CustomerAccountRecoverRequestEvent::EVENT_NAME,
            static function (CustomerAccountRecoverRequestEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password',
                [
                    'email' => 'foo-test@test.de',
                    'storefrontUrl' => $domainUrlTest['expectDomain'],
                ]
            );

        static::assertEquals(200, $this->browser->getResponse()->getStatusCode());

        /** @var CustomerAccountRecoverRequestEvent $caughtEvent */
        static::assertInstanceOf(CustomerAccountRecoverRequestEvent::class, $caughtEvent);
        static::assertStringStartsWith('http://my-evil-page/account/', $caughtEvent->getResetUrl());
    }

    public function testSendMailWithChangedUrl(): void
    {
        $this->createCustomer('shopware1234', 'foo-test@test.de');

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.loginRegistration.pwdRecoverUrl', '/test/rec/password/%%RECOVERHASH%%"');

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $caughtEvent = null;
        $dispatcher->addListener(
            CustomerAccountRecoverRequestEvent::EVENT_NAME,
            static function (CustomerAccountRecoverRequestEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $dispatcher->addListener(
            PasswordRecoveryUrlEvent::class,
            static function (PasswordRecoveryUrlEvent $event): void {
                $event->setRecoveryUrl($event->getRecoveryUrl() . '/?somethingSpecial=1');
            }
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password',
                [
                    'email' => 'foo-test@test.de',
                    'storefrontUrl' => 'http://localhost',
                ]
            );

        static::assertEquals(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        /** @var CustomerAccountRecoverRequestEvent $caughtEvent */
        static::assertInstanceOf(CustomerAccountRecoverRequestEvent::class, $caughtEvent);
        static::assertStringStartsWith('http://localhost/test/rec/password/', $caughtEvent->getResetUrl());
        static::assertStringEndsWith('/?somethingSpecial=1', $caughtEvent->getResetUrl());
    }

    public function sendMailWithDomainAndLeadingSlashProvider()
    {
        return [
            // test without leading slash
            [
                ['domain' => 'http://my-evil-page', 'expectDomain' => 'http://my-evil-page'],
            ],
            // test with leading slash
            [
                ['domain' => 'http://my-evil-page/', 'expectDomain' => 'http://my-evil-page'],
            ],
            // test with double leading slash
            [
                ['domain' => 'http://my-evil-page//', 'expectDomain' => 'http://my-evil-page'],
            ],
        ];
    }

    private function addDomain(string $url): void
    {
        $snippetSetId = $this->getContainer()->get(Connection::class)
            ->fetchColumn('SELECT LOWER(HEX(id)) FROM snippet_set LIMIT 1');

        $domain = [
            'salesChannelId' => $this->ids->create('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'url' => $url,
            'currencyId' => Defaults::CURRENCY,
            'snippetSetId' => $snippetSetId,
        ];

        $this->getContainer()->get('sales_channel_domain.repository')
            ->create([$domain], Context::createDefaultContext());
    }

    private function createCustomer(string $password, ?string $email = null, bool $active = true): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->customerRepository->create([
            [
                'id' => $customerId,
                'active' => $active,
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
}
