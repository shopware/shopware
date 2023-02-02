<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Framework\Test\Store\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
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
        $response = new Response(200, [], '{"payload":"data"}');

        $middleware = new StoreSessionExpiredMiddleware(
            $this->getContainer()->get(Connection::class),
            new RequestStack()
        );

        $handledResponse = $middleware($response);

        static::assertSame($response, $handledResponse);
    }

    public function testReturnsResponseWithRewoundBodyIfCodeIsNotMatched(): void
    {
        $response = new Response(401, [], '{"payload":"data"}');

        $middleware = new StoreSessionExpiredMiddleware(
            $this->getContainer()->get(Connection::class),
            new RequestStack()
        );

        $handledResponse = $middleware($response);

        static::assertSame($response, $handledResponse);
    }

    /**
     * @dataProvider provideRequestStacks
     */
    public function testThrowsIfApiRespondsWithTokenExpiredException(RequestStack $requestStack): void
    {
        $response = new Response(401, [], '{"code":"ShopwarePlatformException-1"}');

        $middleware = new StoreSessionExpiredMiddleware(
            $this->getContainer()->get(Connection::class),
            $requestStack
        );

        $this->expectException(StoreSessionExpiredException::class);
        $middleware($response);
    }

    public function testLogsOutUserAndThrowsIfApiRespondsWithTokenExpiredException(): void
    {
        $response = new Response(401, [], '{"code":"ShopwarePlatformException-1"}');

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

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $middleware = new StoreSessionExpiredMiddleware(
            $this->getContainer()->get(Connection::class),
            $requestStack
        );

        $this->expectException(StoreSessionExpiredException::class);
        $middleware($response);

        $adminUser = $userRepository->search(new Criteria([$adminUser->getId()]), Context::createDefaultContext())->first();
        static::assertNull($adminUser->getStoreToken());
    }

    public function provideRequestStacks(): \Generator
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
