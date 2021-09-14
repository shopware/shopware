<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\TestDefaults;

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

    public function testStoreApiPresent(): void
    {
        $browser = $this->createCustomSalesChannelBrowser([
            'id' => TestDefaults::SALES_CHANNEL,
            'languages' => [],
        ]);
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN, '1234');
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_VERSION_ID, '1234');
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_LANGUAGE_ID, '1234');
        $browser->request('GET', '/store-api/checkout/cart');
        $response = $browser->getResponse();

        static::assertTrue($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_VERSION_ID));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
    }
}
