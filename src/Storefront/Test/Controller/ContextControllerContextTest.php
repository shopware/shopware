<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class ContextControllerContextTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    private KernelBrowser $browser;

    private string $testBaseUrl;

    private string $defaultBaseUrl;

    private string $languageId;

    private Router $router;

    protected function setUp(): void
    {
        $this->router = $this->getContainer()->get('router');

        $this->languageId = Uuid::randomHex();
        $localeId = Uuid::randomHex();

        $this->defaultBaseUrl = $_SERVER['APP_URL'];
        $this->testBaseUrl = $_SERVER['APP_URL'] . '/tst-TST';

        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM sales_channel');

        $domains = [
            [
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => $this->defaultBaseUrl,
            ],
            [
                'language' => [
                    'id' => $this->languageId,
                    'name' => 'Test',
                    'locale' => [
                        'id' => $localeId,
                        'name' => 'Test',
                        'code' => 'x-tst-TST',
                        'territory' => 'test',
                    ],
                    'translationCodeId' => $localeId,
                ],
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => $this->testBaseUrl,
            ],
        ];

        $this->browser = $this->createCustomSalesChannelBrowser([
            'domains' => $domains,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM], ['id' => $this->languageId]],
        ]);
    }

    protected function tearDown(): void
    {
        $this->router->getContext()->setBaseUrl('');
    }

    public function testSwitchToUpperCasePath(): void
    {
        $this->browser->request('GET', $this->defaultBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser->request(
            'POST',
            $this->defaultBaseUrl . '/checkout/language',
            ['languageId' => $this->languageId]
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->testBaseUrl . '/', $response->headers->get('Location'));
    }

    public function testSwitchFromUpperCasePath(): void
    {
        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/checkout/language',
            ['languageId' => Defaults::LANGUAGE_SYSTEM]
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->defaultBaseUrl . '/', $response->headers->get('Location'));
    }

    public function testConfigure(): void
    {
        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $contextSubscriber = new ContextControllerTestSubscriber();
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->addSubscriber($contextSubscriber);

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/checkout/configure',
            ['languageId' => $this->languageId]
        );

        $response = $this->browser->getResponse();

        $dispatcher->removeSubscriber($contextSubscriber);

        static::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->languageId, $contextSubscriber->switchEvent->getRequestDataBag()->get('languageId'));
    }
}

/**
 * @internal
 */
class ContextControllerTestSubscriber implements EventSubscriberInterface
{
    public SalesChannelContextSwitchEvent $switchEvent;

    public static function getSubscribedEvents(): array
    {
        return [
            SalesChannelContextSwitchEvent::class => 'onSwitch',
        ];
    }

    public function onSwitch(SalesChannelContextSwitchEvent $event): void
    {
        $this->switchEvent = $event;
    }
}
