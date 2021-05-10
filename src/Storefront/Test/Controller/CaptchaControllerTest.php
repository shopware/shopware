<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Storefront\Controller\CaptchaController;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Shopware\Storefront\Pagelet\Captcha\BasicCaptchaPagelet;

class CaptchaControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private CaptchaController $controller;

    public function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12455', $this);
        parent::setUp();
        $this->controller = $this->getContainer()->get(CaptchaController::class);
    }

    public function testLoadBasicCaptchaContent(): void
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());

        $browser->request('GET', $_SERVER['APP_URL'] . '/basic-captcha');
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode());
        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertInstanceOf(BasicCaptchaPagelet::class, $response->getData()['page']);
    }
}
