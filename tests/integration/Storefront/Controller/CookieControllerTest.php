<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRoute;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV2;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
class CookieControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private TestBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = KernelLifecycleManager::createBrowser($this->getKernel());
    }

    public function testLegacyCookieProviderRemainsAvailable(): void
    {
        if (Feature::has('v6.9.0.0')) {
            Feature::skipTestIfActive('v6.9.0.0', $this);
        }

        $legacy = static::getContainer()->get(CookieProviderInterface::class);

        static::assertInstanceOf(CookieProviderInterface::class, $legacy);
        static::assertSame(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED, $legacy->getCookieGroups()[0]['snippet_name'] ?? null);
    }

    public function testCoreCookieRouteWorksWithoutAStorefrontProvider(): void
    {
        $container = new ContainerBuilder();
        $dispatcher = new EventDispatcher();
        $container->set(EventDispatcherInterface::class, $dispatcher);
        $container->set(ExtensionDispatcher::class, new ExtensionDispatcher($dispatcher));
        $container->set(ScriptExecutor::class, static::createStub(ScriptExecutor::class));
        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $container->set('translator', $translator);
        $container->setParameter('session.storage.options', ['name' => 'core-only-session']);
        $projectRoot = static::getContainer()->getParameter('kernel.project_dir');
        static::assertIsString($projectRoot);
        (new PhpFileLoader($container, new FileLocator($projectRoot . '/src/Core/Content/DependencyInjection')))->load('cookie.php');
        $container->compile();
        $route = $container->get(CookieRoute::class);
        static::assertInstanceOf(CookieRoute::class, $route);

        $groups = $route->getCookieGroups(new Request(), Generator::generateSalesChannelContext())->getCookieGroups();

        static::assertFalse($container->has(CookieProviderInterface::class));
        $required = $groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        static::assertNotNull($required);
        static::assertNotNull($required->getEntries()?->get('core-only-session'));
    }

    public function testLegacyTemplateFieldsKeepConsentControlsWorking(): void
    {
        if (Feature::has('v6.9.0.0')) {
            Feature::skipTestIfActive('v6.9.0.0', $this);
        }

        $injected = false;
        // A real request supplies the Twig globals. The hook simulates an extension providing legacy template data.
        EventHookDispatcher::fromContainer(static::getContainer())->on(StorefrontRenderEvent::class, static function (StorefrontRenderEvent $event) use (&$injected): void {
            if ($event->getView() !== '@Storefront/storefront/layout/cookie/cookie-configuration.html.twig') {
                return;
            }
            $injected = true;
            $event->setParameter('cookieGroups', [
                [
                    'snippet_name' => 'Legacy optional group',
                    'snippet_description' => 'Legacy group description',
                    'isRequired' => false,
                    'entries' => [
                        ['cookie' => 'legacy-visible', 'snippet_name' => 'Legacy entry label', 'snippet_description' => 'Legacy entry description', 'value' => '1', 'expiration' => '30', 'hidden' => false],
                        ['cookie' => 'legacy-hidden', 'snippet_name' => 'Hidden legacy cookie', 'hidden' => true],
                    ],
                ],
                [
                    'snippet_name' => 'Legacy required group',
                    'isRequired' => true,
                    'entries' => [['cookie' => 'legacy-required', 'snippet_name' => 'Required legacy entry']],
                ],
            ]);
        });

        $crawler = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas');

        static::assertTrue($injected);
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertSame('Legacy entry label', trim($crawler->filter('label[for="cookie_legacy-visible"]')->text()));
        static::assertCount(1, $crawler->filterXPath('//p[contains(text(), "Legacy group description")]'));
        static::assertCount(1, $crawler->filterXPath('//p[contains(text(), "Legacy entry description")]'));
        static::assertCount(0, $crawler->filter('#cookie_legacy-hidden'));
        static::assertCount(0, $crawler->filter('#cookie_legacy-visible[checked], #cookie_legacy-visible[disabled]'));
        static::assertCount(1, $crawler->filter('#cookie_legacy-required[checked][disabled][data-cookie-required="true"]'));
        static::assertSame('1', $crawler->filter('#cookie_legacy-visible')->attr('data-cookie-value'));
        static::assertSame('30', $crawler->filter('#cookie_legacy-visible')->attr('data-cookie-expiration'));
    }

    public function testCookieGroupIncludeComfortFeatures(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.cart.wishlistEnabled', true);

        $crawler = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas');

        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_comfort-features"]'));
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_wishlist-enabled"]'));
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_youtube-video"]'));
    }

    public function testCookieGroupNotIncludeWishlistInComfortFeatures(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.cart.wishlistEnabled', false);

        $crawler = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas');

        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_comfort-features"]'));
        static::assertCount(0, $crawler->filterXPath('//input[@id="cookie_wishlist-enabled"]'));
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_youtube-video"]'));
    }

    public function testCookieRequiredGroupIncludeGoogleReCaptchaWhenActive(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.basicInformation.activeCaptchasV2', [
            GoogleReCaptchaV2::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV2::CAPTCHA_NAME,
                'isActive' => false,
                'config' => [
                    'siteKey' => 'siteKey',
                    'secretKey' => 'secretKey',
                    'invisible' => false,
                ],
            ],
            GoogleReCaptchaV3::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV3::CAPTCHA_NAME,
                'isActive' => false,
                'config' => [
                    'siteKey' => 'siteKey',
                    'secretKey' => 'secretKey',
                    'invisible' => false,
                ],
            ],
        ]);

        $crawler = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas');

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_technically-required"]'));
        static::assertCount(0, $crawler->filterXPath('//input[@id="cookie__GRECAPTCHA"]'));

        $systemConfig->set('core.basicInformation.activeCaptchasV2', [
            GoogleReCaptchaV2::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV2::CAPTCHA_NAME,
                'isActive' => true,
                'config' => [
                    'siteKey' => 'siteKey',
                    'secretKey' => 'secretKey',
                    'invisible' => false,
                ],
            ],
        ]);

        $crawler = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas');

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_technically-required"]'));
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie__GRECAPTCHA"]'));

        $systemConfig->set('core.basicInformation.activeCaptchasV3', [
            GoogleReCaptchaV3::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV3::CAPTCHA_NAME,
                'isActive' => true,
                'config' => [
                    'siteKey' => 'siteKey',
                    'secretKey' => 'secretKey',
                    'invisible' => false,
                ],
            ],
        ]);

        $crawler = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas');

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_technically-required"]'));
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie__GRECAPTCHA"]'));
    }

    public function testConsentOffcanvasRouteRendersWithParameters(): void
    {
        $crawler = $this->browser->request(
            'GET',
            $_SERVER['APP_URL'] . '/cookie/consent-offcanvas?featureName=feature&cookieName=cookieName'
        );

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        static::assertCount(1, $crawler->filterXPath('//div[@class="offcanvas-cookie"]'));
        $content = $this->browser->getResponse()->getContent();

        static::assertNotFalse($content);
        static::assertStringContainsString('cookie.feature.title', $content);
        static::assertStringContainsString('js-wishlist-cookie-accept', $content);
    }
}
