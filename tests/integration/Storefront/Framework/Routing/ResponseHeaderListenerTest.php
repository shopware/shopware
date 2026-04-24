<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class ResponseHeaderListenerTest extends TestCase
{
    use EnvTestBehaviour;
    use SalesChannelFunctionalTestBehaviour;

    public function testHomeController(): void
    {
        $browser = KernelLifecycleManager::createBrowser(KernelLifecycleManager::getKernel());
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN, '1234');
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_VERSION_ID, '1234');
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_LANGUAGE_ID, '1234');
        $browser->request('GET', $_SERVER['APP_URL']);
        $response = $browser->getResponse();

        static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertFalse($response->headers->has(PlatformRequest::HEADER_VERSION_ID));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testNotFoundPage(): void
    {
        $this->setEnvVars(['APP_DEBUG' => false]);
        $kernel = KernelLifecycleManager::bootKernel();

        $browser = KernelLifecycleManager::createBrowser($kernel);
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN, '1234');
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_VERSION_ID, '1234');
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM);

        $browser->request('GET', $_SERVER['APP_URL'] . '/not-found');
        $response = $browser->getResponse();

        static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertFalse($response->headers->has(PlatformRequest::HEADER_VERSION_ID));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testStoreApiPresent(): void
    {
        $browser = $this->createCustomSalesChannelBrowser([
            'id' => TestDefaults::SALES_CHANNEL,
            'languages' => [],
        ]);
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN, '1234');
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_VERSION_ID, '1234');
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_LANGUAGE_ID, Uuid::randomHex());
        $browser->request('GET', '/store-api/checkout/cart');
        $response = $browser->getResponse();

        static::assertTrue($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_VERSION_ID));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
    }
}
