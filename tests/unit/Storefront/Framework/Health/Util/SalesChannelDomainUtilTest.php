<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Health\Util;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(SalesChannelDomainUtil::class)]
class SalesChannelDomainUtilTest extends TestCase
{
    private Connection&MockObject $connection;

    private RouterInterface&MockObject $router;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
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

    private function getUtil(): SalesChannelDomainUtil
    {
        return new SalesChannelDomainUtil(
            $this->connection,
            $this->router,
            $this->requestStack
        );
    }
}
