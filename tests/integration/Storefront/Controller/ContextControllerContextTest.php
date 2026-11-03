<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopware\Storefront\Framework\Routing\Router;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class ContextControllerContextTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    private KernelBrowser $browser;

    private string $testBaseUrl;

    private string $defaultBaseUrl;

    private string $languageId;

    private string $salesChannelId;

    private Router $router;

    protected function setUp(): void
    {
        $this->router = static::getContainer()->get('router');

        $this->languageId = Uuid::randomHex();
        $this->salesChannelId = Uuid::randomHex();
        $localeId = Uuid::randomHex();

        $this->defaultBaseUrl = $_SERVER['APP_URL'];
        $this->testBaseUrl = $_SERVER['APP_URL'] . '/tst-TST';

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM sales_channel');

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
                    'active' => true,
                    'locale' => [
                        'id' => $localeId,
                        'name' => 'Test',
                        'code' => 'af_ZA',
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
            'id' => $this->salesChannelId,
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

    public function testSwitchWithWrongRedirectTo(): void
    {
        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/checkout/language',
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => 'frontend.homer.page']
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->defaultBaseUrl . '/', $response->headers->get('Location'));
    }

    public function testSwitchWithProductIdAndCorrectRedirectTo(): void
    {
        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent() ?: '');

        $productId = Uuid::randomHex();

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/checkout/language',
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => ProductPageSeoUrlRoute::ROUTE_NAME, 'redirectParameters' => ['productId' => $productId]]
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->defaultBaseUrl . '/detail/' . $productId, $response->headers->get('Location'));
    }

    public function testSwitchRedirectsToSeoUrlOfNewLanguage(): void
    {
        $productId = Uuid::randomHex();
        $this->createProductSeoUrl($productId, Defaults::LANGUAGE_SYSTEM, 'test-product/SW10003');

        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent() ?: '');

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/checkout/language',
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => ProductPageSeoUrlRoute::ROUTE_NAME, 'redirectParameters' => ['productId' => $productId]]
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->defaultBaseUrl . '/test-product/SW10003', $response->headers->get('Location'));
    }

    public function testSwitchRedirectsToSeoUrlOfNewLanguageAndKeepsQueryParameters(): void
    {
        $productId = Uuid::randomHex();
        $this->createProductSeoUrl($productId, $this->languageId, 'tst-product/SW10003');

        $this->browser->request('GET', $this->defaultBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent() ?: '');

        $this->browser->request(
            'POST',
            $this->defaultBaseUrl . '/checkout/language',
            ['languageId' => $this->languageId, 'redirectTo' => ProductPageSeoUrlRoute::ROUTE_NAME, 'redirectParameters' => ['productId' => $productId, 'offcanvas' => 'menu']]
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->testBaseUrl . '/tst-product/SW10003?offcanvas=menu', $response->headers->get('Location'));
    }

    public function testSwitchRedirectsToSeoUrlWithSchemeOfCurrentRequest(): void
    {
        $productId = Uuid::randomHex();
        $this->createProductSeoUrl($productId, Defaults::LANGUAGE_SYSTEM, 'test-product/SW10003');

        $secureTestBaseUrl = str_replace('http://', 'https://', $this->testBaseUrl);
        static::getContainer()->get('sales_channel_domain.repository')->create([
            [
                'salesChannelId' => $this->salesChannelId,
                'languageId' => $this->languageId,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => $secureTestBaseUrl,
            ],
        ], Context::createDefaultContext());

        $this->browser->request('GET', $secureTestBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent() ?: '');

        $this->browser->request(
            'POST',
            $secureTestBaseUrl . '/checkout/language',
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => ProductPageSeoUrlRoute::ROUTE_NAME, 'redirectParameters' => ['productId' => $productId]]
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame(str_replace('http://', 'https://', $this->defaultBaseUrl) . '/test-product/SW10003', $response->headers->get('Location'));
    }

    public function testSwitchIgnoresSeoUrlOfPreviousLanguage(): void
    {
        $productId = Uuid::randomHex();
        $this->createProductSeoUrl($productId, $this->languageId, 'tst-product/SW10003');

        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent() ?: '');

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/checkout/language',
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => ProductPageSeoUrlRoute::ROUTE_NAME, 'redirectParameters' => ['productId' => $productId]]
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->defaultBaseUrl . '/detail/' . $productId, $response->headers->get('Location'));
    }

    public function testConfigure(): void
    {
        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $contextSubscriber = new ContextControllerTestSubscriber();
        EventHookDispatcher::fromContainer(static::getContainer())->subscribe($contextSubscriber);

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/checkout/configure',
            ['languageId' => $this->languageId]
        );

        $response = $this->browser->getResponse();

        static::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->languageId, $contextSubscriber->switchEvent->getRequestDataBag()->get('languageId'));
    }

    private function createProductSeoUrl(string $productId, string $languageId, string $seoPathInfo): void
    {
        static::getContainer()->get('seo_url.repository')->create([
            [
                'salesChannelId' => $this->salesChannelId,
                'languageId' => $languageId,
                'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
                'foreignKey' => $productId,
                'pathInfo' => '/detail/' . $productId,
                'seoPathInfo' => $seoPathInfo,
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());
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
