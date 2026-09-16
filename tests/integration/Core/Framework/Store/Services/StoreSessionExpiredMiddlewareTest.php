<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Store\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Exception\StoreSessionExpiredException;
use Shopware\Core\Framework\Store\Services\StoreSessionExpiredMiddleware;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('checkout')]
class StoreSessionExpiredMiddlewareTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->userRepository = static::getContainer()->get('user.repository');
    }

    public function testLogsOutUserAndThrowsIfApiRespondsWithTokenExpiredException(): void
    {
        $response = new Response(401, [], '{"code":"ShopwarePlatformException-1"}');

        $adminUser = $this->userRepository->search(new Criteria(), Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($adminUser);
        $this->userRepository->update([[
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
            static::getContainer()->get(Connection::class),
            $requestStack
        );

        $request = new Psr7Request('GET', '/');

        $this->expectException(StoreSessionExpiredException::class);
        $handler = fn (RequestInterface $req, array $options) => new FulfilledPromise($response);
        /** @var PromiseInterface $promise */
        $promise = ($middleware($handler))($request, []);
        $promise->wait();

        $adminUser = $this->userRepository->search(new Criteria([$adminUser->getId()]), Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($adminUser);
        static::assertNull($adminUser->getStoreToken());
    }

    public function testLogsOutUserByTokenAndThrowsIfApiRespondsWithTokenExpiredException(): void
    {
        $response = new Response(401, [], '{"code":"ShopwarePlatformException-1"}');

        $expiredSessionUserId = Uuid::randomHex();
        $loginSessionUserId = Uuid::randomHex();

        $this->createAdminUser($expiredSessionUserId, 'some-invalid-token');
        $this->createAdminUser($loginSessionUserId, 'some-valid-token');

        $request = new Request(attributes: ['sw-context' => new Context(new AdminApiSource($loginSessionUserId))]);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $middleware = new StoreSessionExpiredMiddleware(
            static::getContainer()->get(Connection::class),
            $requestStack
        );

        $request = new Psr7Request('GET', '/', [StoreRequestOptionsProvider::SHOPWARE_PLATFORM_TOKEN_HEADER => 'some-invalid-token']);

        $this->expectException(StoreSessionExpiredException::class);
        $handler = fn (RequestInterface $req, array $options) => new FulfilledPromise($response);
        /** @var PromiseInterface $promise */
        $promise = ($middleware($handler))($request, []);
        $promise->wait();

        $adminUsers = $this->userRepository->search(new Criteria([$expiredSessionUserId, $loginSessionUserId]), Context::createDefaultContext())->getEntities();
        $expiredSessionUser = $adminUsers->get($expiredSessionUserId);
        $loginSessionUser = $adminUsers->get($loginSessionUserId);
        static::assertNotNull($expiredSessionUser);
        static::assertNotNull($loginSessionUser);
        static::assertNull($expiredSessionUser->getStoreToken());
        static::assertSame('some-valid-token', $loginSessionUser->getStoreToken());
    }

    private function createAdminUser(string $userId, string $storeToken): void
    {
        $data = [
            [
                'id' => $userId,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'username' => Uuid::randomHex() . 'foobar',
                'password' => 'asdasdasdasd',
                'firstName' => 'Foo',
                'lastName' => 'Bar',
                'email' => Uuid::randomHex() . '@bar.com',
                'storeToken' => $storeToken,
            ],
        ];

        $this->userRepository->create($data, Context::createDefaultContext());
    }
}
