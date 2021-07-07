<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Newsletter\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterSubscribeUrlEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @group store-api
 */
class NewsletterSubscribeRouteTest extends TestCase
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
     * @var string
     */
    private $salesChannelId;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->salesChannelId = $this->ids->create('sales-channel');

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->salesChannelId,
        ]);
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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);

        $errors = array_column(array_column($response['errors'], 'source'), 'pointer');
        static::assertContains('/storefrontUrl', $errors);
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
                ]
            );

        $count = (int) $this->getContainer()->get(Connection::class)->fetchColumn('SELECT COUNT(*) FROM newsletter_recipient WHERE email = "test@example.com" AND status = "direct"');
        static::assertSame(1, $count);
    }

    public function testResubscribeAfterUnsubscribe(): void
    {
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
        $row = $connection->fetchAssoc('SELECT * FROM newsletter_recipient WHERE email = "test@example.com"');
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
        $row = $connection->fetchAssoc('SELECT * FROM newsletter_recipient WHERE email = "test@example.com"');
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
        $row = $connection->fetchAssoc('SELECT * FROM newsletter_recipient WHERE email = "test@example.com"');
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
        $row = $connection->fetchAssoc('SELECT * FROM newsletter_recipient WHERE email = "test@example.com"');
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
        $dispatcher->addListener(NewsletterRegisterEvent::class, $listener);

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
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.newsletter.doubleOptIn', true);
        $systemConfig->set('core.newsletter.subscribeUrl', '/custom-newsletter/confirm/%%HASHEDEMAIL%%/%%SUBSCRIBEHASH%%');

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $dispatcher->addListener(
            NewsletterSubscribeUrlEvent::class,
            static function (NewsletterSubscribeUrlEvent $event): void {
                $event->setSubscribeUrl($event->getSubscribeUrl() . '?specialParam=false');
            }
        );

        $caughtEvent = null;
        $dispatcher->addListener(
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
    }

    /**
     * @dataProvider subscribeWithDomainAndLeadingSlashProvider
     */
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

        $count = (int) $this->getContainer()->get(Connection::class)->fetchColumn('SELECT COUNT(*) FROM newsletter_recipient WHERE email = "test@example.com" AND status = "direct"');
        static::assertSame(1, $count);
    }

    public function subscribeWithDomainAndLeadingSlashProvider()
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
}
