<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Framework\Routing\ContextTokenSessionWriter;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ContextTokenSessionWriter::class)]
class ContextTokenSessionWriterTest extends TestCase
{
    private const CONTEXT_TOKEN = 'the-handed-over-context-token';

    public function testTokenIsWrittenToTheSessionAndTheRequestHeaders(): void
    {
        $request = $this->createStorefrontRequest();
        $requestStack = new RequestStack([$request]);

        $this->createWriter($requestStack)->write(self::CONTEXT_TOKEN);

        static::assertSame(self::CONTEXT_TOKEN, $request->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame(self::CONTEXT_TOKEN, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testSessionIdIsRotated(): void
    {
        $request = $this->createStorefrontRequest();
        $sessionIdBefore = $request->getSession()->getId();

        $this->createWriter(new RequestStack([$request]))->write(self::CONTEXT_TOKEN);

        static::assertNotSame($sessionIdBefore, $request->getSession()->getId());
        static::assertSame($request->getSession()->getId(), $request->getSession()->get('sessionId'));
    }

    public function testTokenIsWrittenToTheChannelSpecificKeyWhenCustomerBindingIsEnabled(): void
    {
        $salesChannelId = Uuid::randomHex();
        $request = $this->createStorefrontRequest($salesChannelId);

        $configService = new StaticSystemConfigService([
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        $this->createWriter(new RequestStack([$request]), $configService)->write(self::CONTEXT_TOKEN);

        $session = $request->getSession();
        static::assertSame(
            self::CONTEXT_TOKEN,
            $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelId)
        );
        static::assertSame(self::CONTEXT_TOKEN, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testOnlyTheDefaultKeyIsWrittenWhenCustomerBindingIsDisabled(): void
    {
        $salesChannelId = Uuid::randomHex();
        $request = $this->createStorefrontRequest($salesChannelId);

        $this->createWriter(new RequestStack([$request]))->write(self::CONTEXT_TOKEN);

        $session = $request->getSession();
        static::assertFalse($session->has(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelId));
        static::assertSame(self::CONTEXT_TOKEN, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testNothingIsWrittenWithoutAMainRequest(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->never())->method('getBool');

        $this->createWriter(new RequestStack(), $systemConfigService)->write(self::CONTEXT_TOKEN);
    }

    public function testNothingIsWrittenForANonSalesChannelRequest(): void
    {
        $request = $this->createStorefrontRequest();
        $request->attributes->remove(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST);

        $this->createWriter(new RequestStack([$request]))->write(self::CONTEXT_TOKEN);

        static::assertFalse($request->getSession()->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNull($request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testNothingIsWrittenOutsideTheStorefrontRouteScope(): void
    {
        $request = $this->createStorefrontRequest();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['store-api']);

        $this->createWriter(new RequestStack([$request]))->write(self::CONTEXT_TOKEN);

        static::assertFalse($request->getSession()->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNull($request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testNothingIsWrittenWithoutASession(): void
    {
        $request = new Request(attributes: [
            SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
        ]);

        $this->createWriter(new RequestStack([$request]))->write(self::CONTEXT_TOKEN);

        static::assertNull($request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    private function createStorefrontRequest(?string $salesChannelId = null): Request
    {
        $request = new Request(attributes: [
            SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => $salesChannelId,
        ]);

        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request->setSession($session);

        return $request;
    }

    private function createWriter(
        RequestStack $requestStack,
        ?SystemConfigService $systemConfigService = null
    ): ContextTokenSessionWriter {
        return new ContextTokenSessionWriter(
            $requestStack,
            $systemConfigService ?? new StaticSystemConfigService()
        );
    }
}
