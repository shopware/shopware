<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Newsletter\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterSubscribeUrlEvent;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(NewsletterSubscribeRoute::class)]
#[Group('store-api')]
class NewsletterSubscribeRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private string $salesChannelId;

    private SystemConfigService $systemConfig;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->salesChannelId = $this->ids->create('sales-channel');

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->systemConfig = $this->getContainer()->get(SystemConfigService::class);
        static::assertNotNull($this->systemConfig);
        $this->systemConfig->set('core.newsletter.doubleOptIn', false);
    }

    public function testSubscribeToMultipleSalesChannels(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/subscribe',
                [
                    'email' => 'test@example.com',
                    'option' => 'direct',
                    'storefrontUrl' => 'http://localhost',
                    'firstName' => 'Foo',
                    'lastName' => 'Bar',
                ]
            );

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel-2'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://test.localhost',
                ],
            ],
        ]);

        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/subscribe',
                [
                    'email' => 'test@example.com',
                    'option' => 'direct',
                    'storefrontUrl' => 'http://test.localhost',
                    'firstName' => 'Foo',
                    'lastName' => 'Bar',
                ],
            );

        $count = (int) $this->getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM newsletter_recipient WHERE email = "test@example.com" AND status = "direct"');
        static::assertSame(2, $count);
    }

    public function testSubscribeWithoutFields(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/subscribe',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(3, $response['errors']);

        $errors = array_column(array_column($response['errors'], 'source'), 'pointer');

        static::assertContains('/email', $errors);
        static::assertContains('/option', $errors);
        static::assertContains('/storefrontUrl', $errors);
    }

    public function testSubscribeWithInvalidStorefrontUrl(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/subscribe',
                [
                    'email' => 'test@example.com',
                    'option' => 'direct',
                    'storefrontUrl' => 'https://google.de',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);

        $errors = array_column(array_column($response['errors'], 'source'), 'pointer');
        static::assertContains('/storefrontUrl', $errors);
    }

    public function testResubscribeAfterUnsubscribe(): void
    {
        $this->systemConfig->set('core.newsletter.doubleOptIn', true);

        $connection = $this->getContainer()->get(Connection::class);
        $newsletterRecipientRepository = $this->getContainer()->get('newsletter_recipient.repository');

        // 1: prepare existing user with double opt in
        $firstConfirmedAt = '2020-06-06 00:00:00.000';
        $initData = [
            'email' => 'test@example.com',
            'status' => 'optIn',
            'hash' => 'confirm-hash',
            'salesChannelId' => $this->salesChannelId,
            'confirmedAt' => $firstConfirmedAt,
        ];
        $newsletterRecipientRepository->upsert([$initData], Context::createDefaultContext());

        // 2: validate start data
        /** @var array<string, string|null> $row */
        $row = $connection->fetchAssociative('SELECT * FROM newsletter_recipient WHERE email = "test@example.com"');
        static::assertSame('optIn', $row['status']);
        static::assertSame($firstConfirmedAt, $row['confirmed_at']);

        // 3: unsubscribe
        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/unsubscribe',
                [
                    'email' => 'test@example.com',
                ]
            );

        static::assertTrue($this->browser->getResponse()->isSuccessful());
        /** @var array<string, string|null> $row */
        $row = $connection->fetchAssociative('SELECT * FROM newsletter_recipient WHERE email = "test@example.com"');
        static::assertSame('optOut', $row['status']);
        static::assertNotNull($row['confirmed_at']);

        // 4: resubscribe
        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/subscribe',
                [
                    'email' => 'test@example.com',
                    'option' => 'subscribe',
                    'storefrontUrl' => 'http://localhost',
                ]
            );

        static::assertTrue($this->browser->getResponse()->isSuccessful());
        /** @var array<string, string|null> $row */
        $row = $connection->fetchAssociative('SELECT * FROM newsletter_recipient WHERE email = "test@example.com"');
        static::assertSame('notSet', $row['status']);
        static::assertNotNull($row['confirmed_at']);

        // 5: confirm double opt-in again
        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/confirm',
                [
                    'email' => 'test@example.com',
                    'hash' => $row['hash'],
                ]
            );

        static::assertTrue($this->browser->getResponse()->isSuccessful());
        /** @var array<string, string|null> $row */
        $row = $connection->fetchAssociative('SELECT * FROM newsletter_recipient WHERE email = "test@example.com"');
        static::assertNotEmpty($row);
        static::assertSame('optIn', $row['status']);
        static::assertNotNull($row['confirmed_at']);
        // the confirmation date should have changed
        static::assertNotSame($row['confirmed_at'], $firstConfirmedAt);
    }

    public function testSubscribeIfAlreadyRegistered(): void
    {
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::never())->method('__invoke');

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $this->addEventListener($dispatcher, NewsletterRegisterEvent::class, $listener);

        $context = Context::createDefaultContext();
        $newsletterRecipientRepository = $this->getContainer()->get('newsletter_recipient.repository');

        $data = [
            'id' => '22bbd935e68e4d64a4ab829bb91b30f1',
            'status' => 'optIn',
            'salesChannelId' => $this->salesChannelId,
            'hash' => Uuid::randomHex(),
            'option' => 'subscribe',
            'email' => 'test@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'confirmedAt' => '2020-07-16 08:14:39.603',
        ];

        $newsletterRecipientRepository->upsert([$data], $context);

        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/subscribe',
                [
                    'status' => 'optIn',
                    'email' => 'test@example.com',
                    'option' => 'subscribe',
                    'storefrontUrl' => 'http://localhost',
                ]
            );
    }

    public function testSubscribeChangedConfirmUrl(): void
    {
        try {
            $this->systemConfig->set('core.newsletter.doubleOptIn', true);
            $this->systemConfig->set('core.newsletter.subscribeUrl', '/custom-newsletter/confirm/%%HASHEDEMAIL%%/%%SUBSCRIBEHASH%%');

            /** @var EventDispatcherInterface $dispatcher */
            $dispatcher = $this->getContainer()->get('event_dispatcher');

            $this->addEventListener(
                $dispatcher,
                NewsletterSubscribeUrlEvent::class,
                static function (NewsletterSubscribeUrlEvent $event): void {
                    $event->setSubscribeUrl($event->getSubscribeUrl() . '?specialParam=false');
                }
            );

            $caughtEvent = null;
            $this->addEventListener(
                $dispatcher,
                NewsletterRegisterEvent::class,
                static function (NewsletterRegisterEvent $event) use (&$caughtEvent): void {
                    $caughtEvent = $event;
                }
            );

            $this->browser
                ->request(
                    'POST',
                    '/store-api/newsletter/subscribe',
                    [
                        'status' => 'optIn',
                        'email' => 'test@example.com',
                        'option' => 'subscribe',
                        'storefrontUrl' => 'http://localhost',
                    ]
                );

            /** @var NewsletterRegisterEvent $caughtEvent */
            static::assertInstanceOf(NewsletterRegisterEvent::class, $caughtEvent);
            static::assertStringStartsWith('http://localhost/custom-newsletter/confirm/', $caughtEvent->getUrl());
            static::assertStringEndsWith('?specialParam=false', $caughtEvent->getUrl());
        } finally {
            $this->systemConfig->set('core.newsletter.subscribeUrl', null);
        }
    }

    public function testSubscribeChangedConfirmDomain(): void
    {
        try {
            $this->systemConfig->set('core.newsletter.doubleOptIn', true);
            $this->systemConfig->set('core.newsletter.doubleOptInDomain', 'http://test.test');

            /** @var EventDispatcherInterface $dispatcher */
            $dispatcher = $this->getContainer()->get('event_dispatcher');

            $caughtEvent = null;
            $this->addEventListener(
                $dispatcher,
                NewsletterRegisterEvent::class,
                static function (NewsletterRegisterEvent $event) use (&$caughtEvent): void {
                    $caughtEvent = $event;
                }
            );

            $this->browser
                ->request(
                    'POST',
                    '/store-api/newsletter/subscribe',
                    [
                        'status' => 'optIn',
                        'email' => 'test@example.com',
                        'option' => 'subscribe',
                    ]
                );

            /** @var NewsletterRegisterEvent $caughtEvent */
            static::assertInstanceOf(NewsletterRegisterEvent::class, $caughtEvent);
            static::assertStringStartsWith('http://test.test/newsletter-subscribe?em=', $caughtEvent->getUrl());
        } finally {
            $this->systemConfig->set('core.newsletter.doubleOptInDomain', null, $this->salesChannelId);
        }
    }

    /**
     * @param array<string, string> $domainUrlTest
     */
    #[DataProvider('subscribeWithDomainAndLeadingSlashProvider')]
    public function testSubscribeWithTrailingSlashUrl(array $domainUrlTest): void
    {
        $browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel-newsletter'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => $domainUrlTest['domain'],
                ],
            ],
        ]);

        $browser->request(
            'POST',
            '/store-api/newsletter/subscribe',
            [
                'email' => 'test@example.com',
                'option' => 'direct',
                'storefrontUrl' => $domainUrlTest['expectDomain'],
            ]
        );

        $count = (int) $this->getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM newsletter_recipient WHERE email = "test@example.com" AND status = "direct"');
        static::assertSame(1, $count);
    }

    #[DataProvider('subscribeWithDomainProvider')]
    public function testSubscribeWithInvalid(string $firstName, string $lastName, \Closure $expectClosure): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/subscribe',
                [
                    'email' => 'test@example.com',
                    'option' => 'direct',
                    'storefrontUrl' => 'http://localhost',
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $expectClosure($response);
    }

    public function testSubscribe(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/subscribe',
                [
                    'email' => 'test@example.com',
                    'option' => 'direct',
                    'storefrontUrl' => 'http://localhost',
                    'firstName' => 'Y',
                    'lastName' => 'Tran',
                ]
            );

        $count = (int) $this->getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM newsletter_recipient WHERE email = "test@example.com" AND status = "direct"');
        static::assertSame(1, $count);
    }

    public static function subscribeWithDomainProvider(): \Generator
    {
        yield 'invalid with first name' => [
            'Y http:/shopware.test',
            'Tran',
            function (array $response): void {
                static::assertArrayHasKey('errors', $response);
                static::assertCount(1, $response['errors']);

                $errors = array_column(array_column($response['errors'], 'source'), 'pointer');

                static::assertContains('/firstName', $errors);
            },
        ];

        yield 'invalid with last name' => [
            'Y',
            'Tran https:/shopware.test',
            function (array $response): void {
                static::assertArrayHasKey('errors', $response);
                static::assertCount(1, $response['errors']);

                $errors = array_column(array_column($response['errors'], 'source'), 'pointer');

                static::assertContains('/lastName', $errors);
            },
        ];

        yield 'invalid with domain name *://' => [
            'Y http://shopware.test',
            'Tran https://shopware.test',
            function (array $response): void {
                static::assertArrayHasKey('errors', $response);
                static::assertCount(2, $response['errors']);

                $errors = array_column(array_column($response['errors'], 'source'), 'pointer');

                static::assertContains('/firstName', $errors);
                static::assertContains('/lastName', $errors);
            },
        ];

        yield 'invalid with domain name *:/' => [
            'Y http:/shopware.test',
            'Tran https:/shopware.test',
            function (array $response): void {
                static::assertArrayHasKey('errors', $response);
                static::assertCount(2, $response['errors']);

                $errors = array_column(array_column($response['errors'], 'source'), 'pointer');

                static::assertContains('/firstName', $errors);
                static::assertContains('/lastName', $errors);
            },
        ];
    }

    public static function subscribeWithDomainAndLeadingSlashProvider(): \Generator
    {
        yield 'test without leading slash' => [['domain' => 'http://my-evil-page', 'expectDomain' => 'http://my-evil-page']];

        yield 'test with leading slash' => [['domain' => 'http://my-evil-page/', 'expectDomain' => 'http://my-evil-page']];

        yield 'test with double leading slash' => [['domain' => 'http://my-evil-page//', 'expectDomain' => 'http://my-evil-page']];
    }
}
