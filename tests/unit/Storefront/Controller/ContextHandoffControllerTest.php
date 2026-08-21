<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\ContextHandoffTokenResponse;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextHandoffGenerateRoute;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextHandoffRedeemRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\Struct\ContextHandoffTokenResponseStruct;
use Shopware\Storefront\Controller\ContextHandoffController;
use Shopware\Storefront\Framework\Routing\ContextTokenSessionWriter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextHandoffController::class)]
class ContextHandoffControllerTest extends TestCase
{
    private const CONTEXT_TOKEN = 'the-handed-over-context-token';

    public function testGenerateReturnsTheRouteResultAsJson(): void
    {
        $generateRoute = static::createStub(AbstractContextHandoffGenerateRoute::class);
        $generateRoute->method('generate')->willReturn(new ContextHandoffTokenResponse(
            new ContextHandoffTokenResponseStruct('the-handoff-token', '2026-08-18T12:01:00+00:00')
        ));

        $response = $this->createController(generateRoute: $generateRoute)
            ->generate(static::createStub(SalesChannelContext::class));

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame(
            ['token' => 'the-handoff-token', 'expiresAt' => '2026-08-18T12:01:00+00:00'],
            json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testRedeemWritesTheResolvedTokenIntoSessionAndRequest(): void
    {
        $redeemRoute = static::createStub(AbstractContextHandoffRedeemRoute::class);
        $redeemRoute->method('redeem')->willReturn(new ContextTokenResponse(self::CONTEXT_TOKEN));

        $sessionWriter = $this->createMock(ContextTokenSessionWriter::class);
        $sessionWriter->expects($this->once())->method('write')->with(self::CONTEXT_TOKEN);

        $request = new Request();

        $response = $this->createController(redeemRoute: $redeemRoute, sessionWriter: $sessionWriter)
            ->redeem($request, new RequestDataBag(['token' => 'the-handoff-token']), static::createStub(SalesChannelContext::class));

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame(self::CONTEXT_TOKEN, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    private function createController(
        ?AbstractContextHandoffGenerateRoute $generateRoute = null,
        ?AbstractContextHandoffRedeemRoute $redeemRoute = null,
        ?ContextTokenSessionWriter $sessionWriter = null,
    ): ContextHandoffController {
        return new ContextHandoffController(
            $generateRoute ?? static::createStub(AbstractContextHandoffGenerateRoute::class),
            $redeemRoute ?? static::createStub(AbstractContextHandoffRedeemRoute::class),
            $sessionWriter ?? static::createStub(ContextTokenSessionWriter::class),
        );
    }
}
