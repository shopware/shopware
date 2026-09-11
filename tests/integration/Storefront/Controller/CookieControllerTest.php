<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV2;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3;
use Symfony\Component\HttpFoundation\Response;

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

    public function testLogConsentStoresNothingWhileLoggingIsOff(): void
    {
        // The default storage is "none", the route still answers so a beacon never fails
        $this->browser->request('POST', $_SERVER['APP_URL'] . '/cookie/consent-log', [], [], ['CONTENT_TYPE' => 'application/json'], '{"consentId": "visitor-a", "consentAction": "accept_all"}');

        static::assertSame(Response::HTTP_NO_CONTENT, $this->browser->getResponse()->getStatusCode());
        static::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM `cookie_consent_log`'));
    }

    public function testLogConsentRejectsInvalidPayload(): void
    {
        $this->browser->request('POST', $_SERVER['APP_URL'] . '/cookie/consent-log', [], [], ['CONTENT_TYPE' => 'application/json'], '{"consentId": "visitor-a", "consentAction": "invalid"}');

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
    }

    public function testConsentLogRouteIsNotExposedToTheStorefrontWhileLoggingIsOff(): void
    {
        $this->browser->request('GET', $_SERVER['APP_URL'] . '/');

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertStringNotContainsString('frontend.cookie.consent.log', (string) $this->browser->getResponse()->getContent());
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
