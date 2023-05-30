<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreClientFactory;
use Shopware\Core\Framework\Store\Services\VerifyResponseSignatureMiddleware;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('merchant-services')]
class StoreClientFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const TEST_STORE_URI = 'http://test-store';

    private const STORE_URI_CONFIG_KEY = 'core.store.apiUri';

    private ?string $originalStoreUri = null;

    /**
     * @before
     */
    public function updateStoreUri(): void
    {
        $this->originalStoreUri = $this->getApiUrlFromSystemConfig();
        $this->getSystemConfigService()->set(self::STORE_URI_CONFIG_KEY, self::TEST_STORE_URI);
    }

    /**
     * @after
     */
    public function restoreStoreUri(): void
    {
        $this->getSystemConfigService()->set(self::STORE_URI_CONFIG_KEY, $this->originalStoreUri);
    }

    public function testItCreatesAnClientWithBaseConfig(): void
    {
        $storeClientFactory = new StoreClientFactory($this->getSystemConfigService());

        $client = $storeClientFactory->create([$this->createMock(VerifyResponseSignatureMiddleware::class)]);
        $config = $this->getConfigFromClient($client);

        static::assertEquals(self::TEST_STORE_URI, $config['base_uri']);

        static::assertArrayHasKey('Content-Type', $config['headers']);
        static::assertEquals('application/json', $config['headers']['Content-Type']);

        static::assertArrayHasKey('Accept', $config['headers']);
        static::assertEquals('application/vnd.api+json,application/json', $config['headers']['Accept']);

        /** @var HandlerStack $stack */
        $stack = $config['handler'];

        static::assertTrue($stack->hasHandler());
    }

    private function getSystemConfigService(): SystemConfigService
    {
        return $this->getContainer()->get(SystemConfigService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfigFromClient(ClientInterface $client): array
    {
        $reflObject = new \ReflectionObject($client);
        $reflProp = $reflObject->getProperty('config');

        $reflProp->setAccessible(true);

        return $reflProp->getValue($client);
    }

    private function getApiUrlFromSystemConfig(): string
    {
        $apiUrl = $this->getSystemConfigService()->get(self::STORE_URI_CONFIG_KEY);

        static::assertIsString($apiUrl);

        return $apiUrl;
    }
}
