<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Shopware\Core\Framework\Store\Exception\ShopSecretInvalidException;
use Shopware\Core\Framework\Store\Services\ShopSecretInvalidMiddleware;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class ShopSecretInvalidMiddlewareTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
    }

    public function testKeepsStoreTokensAndReturnsResponse(): void
    {
        $this->setAllUserStoreTokens('secret_token');
        $this->systemConfigService->set('core.store.shopSecret', 'shop-s3cr3t-token');

        $body = $this->createMock(StreamInterface::class);
        $body->method('getContents')->willReturn('{"payload":"data"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);

        $middleware = new ShopSecretInvalidMiddleware($this->connection, $this->systemConfigService);

        $handledResponse = $middleware($response);

        static::assertSame($response, $handledResponse);

        foreach ($this->fetchAllUserStoreTokens() as $token) {
            static::assertSame('secret_token', $token['store_token']);
        }

        static::assertSame('shop-s3cr3t-token', $this->systemConfigService->get('core.store.shopSecret'));
    }

    public function testKeepsStoreTokensAndReturnsResponseWithRewoundBody(): void
    {
        $this->setAllUserStoreTokens('secret_token');
        $this->systemConfigService->set('core.store.shopSecret', 'shop-s3cr3t-token');

        $body = $this->createMock(StreamInterface::class);
        $body->method('getContents')->willReturn('{"payload":"data"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('getBody')->willReturn($body);

        $middleware = new ShopSecretInvalidMiddleware($this->connection, $this->systemConfigService);

        $handledResponse = $middleware($response);

        static::assertSame($response, $handledResponse);

        foreach ($this->fetchAllUserStoreTokens() as $token) {
            static::assertSame('secret_token', $token['store_token']);
        }

        static::assertSame('shop-s3cr3t-token', $this->systemConfigService->get('core.store.shopSecret'));
    }

    public function testThrowsAndDeletesStoreTokensIfApiRespondsWithTokenExpiredException(): void
    {
        $this->setAllUserStoreTokens('secret_token');

        $body = $this->createMock(StreamInterface::class);
        $body->method('getContents')->willReturn('{"code":"ShopwarePlatformException-68"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('getBody')->willReturn($body);

        $middleware = new ShopSecretInvalidMiddleware($this->connection, $this->systemConfigService);

        $this->expectException(ShopSecretInvalidException::class);
        $middleware($response);

        foreach ($this->fetchAllUserStoreTokens() as $token) {
            static::assertNull($token);
        }

        static::assertNull($this->systemConfigService->get('core.store.shopSecret'));
    }

    private function setAllUserStoreTokens(string $storeToken): void
    {
        $this->connection->executeStatement('UPDATE user SET store_token = :storeToken', ['storeToken' => $storeToken]);
    }

    private function fetchAllUserStoreTokens(): array
    {
        return $this->connection->executeQuery('SELECT store_token FROM user')->fetchAllAssociative();
    }
}
