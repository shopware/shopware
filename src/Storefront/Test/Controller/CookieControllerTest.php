<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\CookieController;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3;

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
        Feature::skipTestIfInActive('FEATURE_NEXT_12455', $this);

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.basicInformation.activeCaptchas', []);

        $response = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas', []);

        static::assertCount(1, $response->filterXPath('//input[@id="cookie_Technically required"]'));
        static::assertCount(0, $response->filterXPath('//input[@id="cookie__GRECAPTCHA"]'));

        $systemConfig->set('core.basicInformation.activeCaptchas', [GoogleReCaptchaV3::CAPTCHA_NAME]);

        $response = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas', []);

        static::assertCount(1, $response->filterXPath('//input[@id="cookie_Technically required"]'));
        static::assertCount(1, $response->filterXPath('//input[@id="cookie__GRECAPTCHA"]'));
    }
}
