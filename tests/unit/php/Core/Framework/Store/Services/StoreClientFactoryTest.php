<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreClientFactory;
use Shopware\Core\Framework\Store\Services\VerifyResponseSignatureMiddleware;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Store\Services\StoreClientFactory
 */
#[Package('merchant-services')]
class StoreClientFactoryTest extends TestCase
{
    public function testCreatesClientWithoutMiddlewares(): void
    {
        $factory = new StoreClientFactory($this->createSystemConfigService());

        $client = $factory->create();
        $config = $this->getConfigFromClient($client);
        $handler = $this->getHandlerFromConfig($config);

        static::assertTrue($handler->hasHandler());
    }

    public function testCreatesClientWithMiddlewares(): void
    {
        $factory = new StoreClientFactory($this->createSystemConfigService());

        $client = $factory->create([$this->createMock(VerifyResponseSignatureMiddleware::class)]);
        $config = $this->getConfigFromClient($client);
        $handler = $this->getHandlerFromConfig($config);

        static::assertTrue($handler->hasHandler());
    }

    private function createSystemConfigService(): SystemConfigService & MockObject
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getString')
            ->willReturn('http://shopware.swag');

        return $systemConfigService;
    }

    /**
     * @return array{handler: HandlerStack, base_uri: string, headers: array<string, string>}
     */
    private function getConfigFromClient(ClientInterface $client): array
    {
        $reflection = new \ReflectionClass($client);
        $config = $reflection->getProperty('config')->getValue($client);

        static::assertIsArray($config);
        static::assertArrayHasKey('base_uri', $config);
        static::assertArrayHasKey('handler', $config);
        static::assertArrayHasKey('headers', $config);

        return $config;
    }

    /**
     * @param array{handler: HandlerStack} $config
     */
    private function getHandlerFromConfig(array $config): HandlerStack
    {
        $handler = $config['handler'];

        static::assertInstanceOf(HandlerStack::class, $handler);

        return $handler;
    }
}
