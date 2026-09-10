<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\ServiceClientFactory;
use Shopware\Core\Service\ServiceRegistry\Client as ServiceRegistryClient;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceClientFactory::class)]
class ServiceClientFactoryTest extends TestCase
{
    public function testNewForServiceRegistryEntry(): void
    {
        $scopedClient = $this->createMock(HttpClientInterface::class);
        $scopedClient->expects($this->never())->method('request');
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('withOptions')
            ->with([
                'base_uri' => 'https://mycoolservice.com',
            ])
            ->willReturn($scopedClient);

        $serviceClientRegistry = static::createStub(ServiceRegistryClient::class);

        $clientFactory = new ServiceClientFactory($httpClient, $serviceClientRegistry, '6.6.0.0');
        $client = $clientFactory->newFor(new ServiceEntry('MyCoolService', 'My Cool Service', 'https://mycoolservice.com', '/app-endpoint'));

        static::assertSame($scopedClient, $client->client);
    }

    public function testFromNameProxiesToServiceRegistryClient(): void
    {
        $scopedClient = $this->createMock(HttpClientInterface::class);
        $scopedClient->expects($this->never())->method('request');
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('withOptions')
            ->with([
                'base_uri' => 'https://mycoolservice.com',
            ])
            ->willReturn($scopedClient);
        $serviceClientRegistry = static::createMock(ServiceRegistryClient::class);
        $serviceClientRegistry->expects($this->once())
            ->method('get')
            ->with('MyCoolService')
            ->willReturn(new ServiceEntry('MyCoolService', 'My Cool Service', 'https://mycoolservice.com', '/app-endpoint'));

        $clientFactory = new ServiceClientFactory($httpClient, $serviceClientRegistry, '6.6.0.0');
        $client = $clientFactory->fromName('MyCoolService');

        static::assertSame($scopedClient, $client->client);
    }
}
