<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Services\ServiceClientFactory;
use Shopware\Core\Services\ServiceRegistryClient;
use Shopware\Core\Services\ServiceRegistryEntry;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[CoversClass(ServiceClientFactory::class)]
class ServiceClientFactoryTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;

    private HttpClientInterface&MockObject $scopedClient;

    protected function setUp(): void
    {
        $this->scopedClient = $this->createMock(HttpClientInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->httpClient
            ->expects(static::once())
            ->method('withOptions')
            ->with([
                'base_uri' => 'https://mycoolservice.com',
            ])
            ->willReturn($this->scopedClient);
    }

    public function testNewForServiceRegistryEntry(): void
    {
        $serviceClientRegistry = static::createMock(ServiceRegistryClient::class);

        $clientFactory = new ServiceClientFactory($this->httpClient, $serviceClientRegistry, '6.6.0.0');
        $client = $clientFactory->newFor(new ServiceRegistryEntry('MyCoolService', 'My Cool Service', 'https://mycoolservice.com', '/app-endpoint'));

        static::assertSame($this->scopedClient, $client->client);
    }

    public function testFromNameProxiesToServiceRegistryClient(): void
    {
        $serviceClientRegistry = static::createMock(ServiceRegistryClient::class);
        $serviceClientRegistry->expects(static::once())
            ->method('get')
            ->with('MyCoolService')
            ->willReturn(new ServiceRegistryEntry('MyCoolService', 'My Cool Service', 'https://mycoolservice.com', '/app-endpoint'));

        $clientFactory = new ServiceClientFactory($this->httpClient, $serviceClientRegistry, '6.6.0.0');
        $client = $clientFactory->fromName('MyCoolService');

        static::assertSame($this->scopedClient, $client->client);
    }
}
