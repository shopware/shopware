<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Health\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(SalesChannelDomainUtil::class)]
class SalesChannelDomainUtilTest extends TestCase
{
    private KernelInterface&MockObject $kernel;

    private RouterInterface&MockObject $router;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->requestStack = new RequestStack();
    }

    public function testRunAsSalesChannelRequest(): void
    {
        $this->requestStack->push(new Request([], [], [
            SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
        ]));

        $util = $this->getUtil();

        $result = $util->runAsSalesChannelRequest(function () {
            return new Result(
                'test',
                Status::OK,
                'Test completed successfully'
            );
        });

        static::assertSame('test', $result->name);
        static::assertSame(Status::OK, $result->status);

        $request = $this->requestStack->getMainRequest();
        static::assertInstanceOf(Request::class, $request);
        static::assertTrue($request->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, false));
    }

    public function testRunAsSalesChannelRequestWithoutMainRequest(): void
    {
        $util = $this->getUtil();

        $result = $util->runAsSalesChannelRequest(function () {
            return new Result(
                'test',
                Status::OK,
                'Test completed successfully'
            );
        });

        static::assertSame('test', $result->name);
        static::assertSame(Status::OK, $result->status);
        static::assertEmpty($this->requestStack->getMainRequest());
    }

    public function testRunWhileTrustingAllHosts(): void
    {
        Request::setTrustedHosts(['example.com']);

        $util = $this->getUtil();

        $result = $util->runWhileTrustingAllHosts(function () {
            // check that trusted hosts are empty during the callback
            static::assertSame([], Request::getTrustedHosts());

            return new Result(
                'test',
                Status::OK,
                'Test completed successfully'
            );
        });

        static::assertSame('test', $result->name);
        static::assertSame(Status::OK, $result->status);

        // setTrustedHosts adds '{' and '}i' around the host
        static::assertSame(['{example.com}i'], Request::getTrustedHosts());

        // Reset trusted hosts to avoid leaking state
        Request::setTrustedHosts([]);
    }

    public function testGenerateDomainUrl(): void
    {
        $url = 'https://example.com';
        $routeName = 'test_route';
        $parameters = ['param1' => 'value1', 'param2' => 'value2'];

        $this->router->expects($this->once())
            ->method('generate')
            ->with($routeName, $parameters)
            ->willReturn('/test/path');

        $util = $this->getUtil();

        $resultUrl = $util->generateDomainUrl($url, $routeName, $parameters);

        static::assertSame('https://example.com/test/path', $resultUrl);
    }

    public function testCreateEmptyResult(): void
    {
        $util = $this->getUtil();

        $result = $util->createEmptyResult('test', 'This is a test message');

        static::assertSame('test', $result->name);
        static::assertSame(Status::SKIPPED, $result->status);
        static::assertSame('This is a test message', $result->message);
        static::assertTrue($result->healthy);
    }

    public function testHandleRequestWithRedirects(): void
    {
        $this->kernel->method('handle')->willReturnOnConsecutiveCalls(
            new RedirectResponse('http://localhost/seo', Response::HTTP_MOVED_PERMANENTLY),
            new RedirectResponse('http://localhost/product/123', Response::HTTP_MOVED_PERMANENTLY),
            new Response(status: Response::HTTP_OK),
        );

        $util = $this->getUtil();
        $request = new Request();

        $result = $util->handleRequest($request);
        static::assertSame('http://localhost/product/123', $result->storefrontUrl);
        static::assertSame(Response::HTTP_OK, $result->responseCode);
    }

    public function testHandleRequestsDetectsLoop(): void
    {
        $this->kernel->method('handle')->willReturnOnConsecutiveCalls(
            ...array_fill(0, 6, new RedirectResponse('http://localhost/product/123', Response::HTTP_MOVED_PERMANENTLY)),
        );

        $util = $this->getUtil();
        $request = new Request();

        $result = $util->handleRequest($request);
        static::assertSame('http://localhost/product/123', $result->storefrontUrl);
        static::assertSame(Response::HTTP_LOOP_DETECTED, $result->responseCode);
    }

    private function getUtil(): SalesChannelDomainUtil
    {
        return new SalesChannelDomainUtil(
            $this->router,
            $this->requestStack,
            $this->kernel
        );
    }
}
