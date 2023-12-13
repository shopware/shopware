<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Exception\StoreSessionExpiredException;
use Shopware\Core\Framework\Store\Services\StoreSessionExpiredMiddleware;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(StoreSessionExpiredMiddleware::class)]
class StoreSessionExpiredMiddlewareTest extends TestCase
{
    public function testReturnsResponseIfStatusCodeIsNotUnauthorized(): void
    {
        $response = new Response(200, [], '{"payload":"data"}');

        $middleware = new StoreSessionExpiredMiddleware(
            $this->createMock(Connection::class),
            new RequestStack(),
        );

        $handledResponse = $middleware($response);

        static::assertSame($response, $handledResponse);
    }

    public function testReturnsResponseWithRewoundBodyIfCodeIsNotMatched(): void
    {
        $response = new Response(401, [], '{"payload":"data"}');

        $middleware = new StoreSessionExpiredMiddleware(
            $this->createMock(Connection::class),
            new RequestStack(),
        );

        $handledResponse = $middleware($response);

        static::assertSame($response, $handledResponse);
    }

    #[DataProvider('provideRequestStacks')]
    public function testThrowsIfApiRespondsWithTokenExpiredException(RequestStack $requestStack): void
    {
        $response = new Response(401, [], '{"code":"ShopwarePlatformException-1"}');

        $middleware = new StoreSessionExpiredMiddleware(
            $this->createMock(Connection::class),
            $requestStack
        );

        $this->expectException(StoreSessionExpiredException::class);
        $middleware($response);
    }

    public function testLogsOutUserAndThrowsIfApiRespondsWithTokenExpiredException(): void
    {
        $response = new Response(401, [], '{"code":"ShopwarePlatformException-1"}');

        $adminUser = new UserEntity();
        $adminUser->setId('592d9499bea4417e929622ed0e92ba8b');

        $context = new Context(new AdminApiSource($adminUser->getId()));

        $request = new Request(
            [],
            [],
            [
                'sw-context' => $context,
            ]
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('executeStatement')
            ->with(static::anything(), ['userId' => Uuid::fromHexToBytes($adminUser->getId())]);

        $middleware = new StoreSessionExpiredMiddleware(
            $connection,
            $requestStack
        );

        $this->expectException(StoreSessionExpiredException::class);
        $middleware($response);
    }

    public static function provideRequestStacks(): \Generator
    {
        yield 'request stack without request' => [new RequestStack()];

        $requestStackWithoutContext = new RequestStack();
        $requestStackWithoutContext->push(new Request());

        yield 'request stack without context' => [$requestStackWithoutContext];

        $requestStackWithWrongSource = new RequestStack();
        $requestStackWithWrongSource->push(new Request([], [], ['sw-context' => Context::createDefaultContext()]));

        yield 'request stack with wrong source' => [$requestStackWithWrongSource];

        $requestStackWithMissingUserId = new RequestStack();
        $requestStackWithMissingUserId->push(new Request([], [], ['sw-context' => new Context(new AdminApiSource(null))]));

        yield 'request stack with missing user id' => [$requestStackWithMissingUserId];
    }
}
