<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\CookieController;

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
        Feature::skipTestIfInActive('FEATURE_NEXT_10549', $this);
        $this->browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $this->cookieController = $this->getContainer()->get(CookieController::class);
    }

    public function testCookieGroupIncludeComfortFeatures(): void
    {
        /** @var SystemConfigService $systemConfig */
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.cart.wishlistEnabled', true);

        $response = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas', []);

        static::assertCount(1, $response->filterXPath('//input[@id="cookie_Comfort features"]'));
        static::assertCount(1, $response->filterXPath('//input[@id="cookie_wishlist-enabled"]'));
    }

    public function testCookieGroupNotIncludeComfortFeatures(): void
    {
        /** @var SystemConfigService $systemConfig */
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.cart.wishlistEnabled', false);

        $response = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas', []);

        static::assertCount(0, $response->filterXPath('//input[@id="cookie_Comfort features"]'));
        static::assertCount(0, $response->filterXPath('//input[@id="cookie_wishlist-enabled"]'));
    }
}
