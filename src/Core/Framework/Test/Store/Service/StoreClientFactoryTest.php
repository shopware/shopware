<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Services\OpenSSLVerifier;
use Shopware\Core\Framework\Store\Services\StoreClientFactory;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SystemConfigTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class StoreClientFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SystemConfigTestBehaviour;

    private const TEST_STORE_URI = 'http://test-store';

    private const STORE_URI_CONFIG_KEY = 'core.store.apiUri';

    private StoreClientFactory $clientFactory;

    private ?string $originalStoreUri;

    public function setUp(): void
    {
        // create new sbp client factory as it is inlined in test environment because clients are created as mocks
        $this->clientFactory = new StoreClientFactory(
            $this->getSystemConfigService(),
            $this->getContainer()->get(OpenSSLVerifier::class)
        );
    }

    /**
     * @before
     */
    public function updateStoreUri(): void
    {
        $this->originalStoreUri = $this->getSystemConfigService()->get(self::STORE_URI_CONFIG_KEY);
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
        $client = $this->clientFactory->create();
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

    private function getConfigFromClient(Client $client): array
    {
        $reflObject = new \ReflectionObject($client);
        $reflProp = $reflObject->getProperty('config');

        $reflProp->setAccessible(true);

        return $reflProp->getValue($client);
    }
}
