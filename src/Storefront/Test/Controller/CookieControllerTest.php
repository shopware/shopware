<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\CookieController;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV2;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3;
use Symfony\Component\HttpFoundation\Response;

class CookieControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var CookieController
     */
    private $cookieController;

    protected function setUp(): void
    {
        $this->browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $this->cookieController = $this->getContainer()->get(CookieController::class);
    }

    public function testCookieGroupIncludeComfortFeatures(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.cart.wishlistEnabled', true);

        $response = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas', []);

        static::assertCount(1, $response->filterXPath('//input[@id="cookie_Comfort features"]'));
        static::assertCount(1, $response->filterXPath('//input[@id="cookie_wishlist-enabled"]'));
    }

    public function testCookieGroupNotIncludeComfortFeatures(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.cart.wishlistEnabled', false);

        $response = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas', []);

        static::assertCount(0, $response->filterXPath('//input[@id="cookie_Comfort features"]'));
        static::assertCount(0, $response->filterXPath('//input[@id="cookie_wishlist-enabled"]'));
    }

    public function testCookieRequiredGroupIncludeGoogleReCaptchaWhenActive(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);

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

        $response = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas', []);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertCount(1, $response->filterXPath('//input[@id="cookie_Technically required"]'));
        static::assertCount(0, $response->filterXPath('//input[@id="cookie__GRECAPTCHA"]'));

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

        $response = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas', []);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertCount(1, $response->filterXPath('//input[@id="cookie_Technically required"]'));
        static::assertCount(1, $response->filterXPath('//input[@id="cookie__GRECAPTCHA"]'));

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

        $response = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas', []);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertCount(1, $response->filterXPath('//input[@id="cookie_Technically required"]'));
        static::assertCount(1, $response->filterXPath('//input[@id="cookie__GRECAPTCHA"]'));
    }
}
