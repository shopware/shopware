<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;

class ResponseHeaderListenerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    public function testHomeController(): void
    {
        $browser = KernelLifecycleManager::createBrowser(KernelLifecycleManager::getKernel(), false);
        $browser->request('GET', $_SERVER['APP_URL']);
        $response = $browser->getResponse();

        static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertFalse($response->headers->has(PlatformRequest::HEADER_VERSION_ID));
        static::assertFalse($response->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testSalesChannelApiPresent(): void
    {
        $browser = $this->createCustomSalesChannelBrowser(['id' => Defaults::SALES_CHANNEL]);
        $browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart');
        $response = $browser->getResponse();

        static::assertTrue($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_VERSION_ID));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
    }
}
