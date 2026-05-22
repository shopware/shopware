<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Framework\Routing\NotFound\NotFoundSubscriber;

/**
 * @internal
 */
class ResponseHeaderListenerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    public function testHomeController(): void
    {
        $browser = $this->createCustomSalesChannelBrowser();
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN, '1234');
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_VERSION_ID, '1234');
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_LANGUAGE_ID, '1234');
        $browser->request('GET', '/');
        $response = $browser->getResponse();

        static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertFalse($response->headers->has(PlatformRequest::HEADER_VERSION_ID));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testNotFoundPage(): void
    {
        try {
            $this->toggleNotFoundSubscriber(false);
            $browser = $this->createCustomSalesChannelBrowser();
            $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN, '1234');
            $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_VERSION_ID, '1234');
            $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM);

            $browser->request('GET', '/not-found');
            $response = $browser->getResponse();

            static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
            static::assertFalse($response->headers->has(PlatformRequest::HEADER_VERSION_ID));
            static::assertTrue($response->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
        } finally {
            $this->toggleNotFoundSubscriber(true);
        }
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

    /**
     * we need to enable the not found subscriber so the 404 page is rendered,
     * that is not enabled by default in the test environment as `APP_DEBUG` is set to false
     */
    private function toggleNotFoundSubscriber(bool $debug): void
    {
        $subscriber = static::getContainer()->get(NotFoundSubscriber::class);
        (new \ReflectionProperty($subscriber::class, 'kernelDebug'))->setValue($subscriber, $debug);
    }
}
