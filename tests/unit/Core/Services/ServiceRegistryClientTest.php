<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Services\ServiceRegistryClient;
use Shopware\Core\Services\ServiceRegistryEntry;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[CoversClass(ServiceRegistryClient::class)]
class ServiceRegistryClientTest extends TestCase
{
    public function testInvalidResponseBodyReturnsEmptyListOfServices(): void
    {
        $client = new MockHttpClient([
            $response = new MockResponse(''),
        ]);

        $registryClient = new ServiceRegistryClient($client, new StaticSystemConfigService([
            'core.services.registryUrl' => 'https://www.shopware.com/services.json',
        ]));

        static::assertEquals([], $registryClient->getAll());
        static::assertEquals('https://www.shopware.com/services.json', $response->getRequestUrl());
    }

    public function testFailRequestReturnsEmptyListOfServices(): void
    {
        $client = new MockHttpClient([
            $response = new MockResponse('', ['http_code' => 503]),
        ]);

        $registryClient = new ServiceRegistryClient($client, new StaticSystemConfigService([
            'core.services.registryUrl' => 'https://www.shopware.com/services.json',
        ]));

        static::assertEquals([], $registryClient->getAll());
        static::assertEquals('https://www.shopware.com/services.json', $response->getRequestUrl());
    }

    public function testSuccessfulRequestReturnsListOfServices(): void
    {
        $services = [
            ['name' => 'MyCoolService1', 'host' => 'https://coolservice1.com', 'label' => 'My Cool Service 1', 'app-endpoint' => '/app-endpoint'],
            ['name' => 'MyCoolService2', 'host' => 'https://coolservice2.com', 'label' => 'My Cool Service 2', 'app-endpoint' => '/app-endpoint'],
        ];

        $client = new MockHttpClient([
            $response = new MockResponse((string) json_encode($services)),
        ]);

        $registryClient = new ServiceRegistryClient($client, new StaticSystemConfigService([
            'core.services.registryUrl' => 'https://www.shopware.com/services.json',
        ]));

        $entries = $registryClient->getAll();
        static::assertCount(2, $entries);
        static::assertContainsOnlyInstancesOf(ServiceRegistryEntry::class, $entries);
        static::assertEquals('MyCoolService1', $entries[0]->name);
        static::assertEquals('My Cool Service 1', $entries[0]->description);
        static::assertEquals('https://coolservice1.com', $entries[0]->host);
        static::assertEquals('/app-endpoint', $entries[0]->appEndpoint);
        static::assertEquals('MyCoolService2', $entries[1]->name);
        static::assertEquals('My Cool Service 2', $entries[1]->description);
        static::assertEquals('https://coolservice2.com', $entries[1]->host);
        static::assertEquals('/app-endpoint', $entries[1]->appEndpoint);
        static::assertEquals('https://www.shopware.com/services.json', $response->getRequestUrl());
    }
}
