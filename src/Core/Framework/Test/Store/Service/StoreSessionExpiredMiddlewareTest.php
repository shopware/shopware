<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Store\Exception\StoreSessionExpiredException;
use Shopware\Core\Framework\Store\Services\StoreSessionExpiredMiddleware;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
class StoreSessionExpiredMiddlewareTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testReturnsResponseIfStatusCodeIsNotUnauthorized(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body->method('getContents')->willReturn('{"payload":"data"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);

        $middleware = new StoreSessionExpiredMiddleware(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('request_stack')
        );

        $handledResponse = $middleware($response);

        static::assertSame($response, $handledResponse);
    }

    public function testReturnsResponseWithRewoundBodyIfCodeIsNotMatched(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body->method('getContents')->willReturn('{"payload":"data"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('getBody')->willReturn($body);

        $middleware = new StoreSessionExpiredMiddleware(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('request_stack')
        );

        $handledResponse = $middleware($response);

        static::assertSame($response, $handledResponse);
    }

    /**
     * @dataProvider provideRequestStacks
     */
    public function testThrowsIfApiRespondsWithTokenExpiredException(RequestStack $requestStack): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body->method('getContents')->willReturn('{"code":"ShopwarePlatformException-1"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('getBody')->willReturn($body);

        $middleware = new StoreSessionExpiredMiddleware(
            $this->getContainer()->get(Connection::class),
            $requestStack
        );

        $this->expectException(StoreSessionExpiredException::class);
        $middleware($response);
    }

    public function testLogsOutUserAndThrowsIfApiRespondsWithTokenExpiredException(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body->method('getContents')->willReturn('{"code":"ShopwarePlatformException-1"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('getBody')->willReturn($body);

        $userRepository = $this->getContainer()->get('user.repository');
        /** @var UserEntity $adminUser */
        $adminUser = $userRepository->search(new Criteria(), Context::createDefaultContext())->first();
        $userRepository->update([[
            'id' => $adminUser->getId(),
            'store_token' => 's3cr3t',
        ]], Context::createDefaultContext());

        $context = new Context(new AdminApiSource($adminUser->getId()));

        $request = new Request(
            [],
            [],
            [
                'sw-context' => $context,
            ]
        );

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $middleware = new StoreSessionExpiredMiddleware(
            $this->getContainer()->get(Connection::class),
            $requestStack
        );

        $this->expectException(StoreSessionExpiredException::class);
        $middleware($response);

        $adminUser = $userRepository->search(new Criteria([$adminUser->getId()]), Context::createDefaultContext())->first();
        static::assertNull($adminUser->getStoreToken());
    }

    public function provideRequestStacks(): array
    {
        $requestStackWithoutRequest = new RequestStack();

        $requestStackWithoutContext = new RequestStack();
        $requestStackWithoutContext->push(new Request());

        $requestStackWithWrongSource = new RequestStack();
        $requestStackWithWrongSource->push(new Request([], [], ['sw-context' => Context::createDefaultContext()]));

        $requestStackWithMissingUserId = new RequestStack();
        $requestStackWithMissingUserId->push(new Request([], [], ['sw-context' => new Context(new AdminApiSource(null))]));

        return [
            [$requestStackWithoutRequest],
            [$requestStackWithoutContext],
            [$requestStackWithWrongSource],
            [$requestStackWithMissingUserId],
        ];
    }
}
