<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Service\ServiceRegistryClient;
use Shopware\Core\Service\ServiceRegistryEntry;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[CoversClass(ServiceRegistryClient::class)]
class ServiceRegistryClientTest extends TestCase
{
    public static function invalidResponseProvider(): \Generator
    {
        yield 'not-json' => [''];

        yield 'not-correct-list' => [json_encode([1, 2, 3])];

        yield 'not-correct-service-definition' => [json_encode([['not-valid' => 1]])];

        yield 'missing-label' => [json_encode([['name' => 'SomeService']])];

        yield 'missing-host' => [json_encode([['name' => 'SomeService', 'label' => 'SomeService']])];

        yield 'missing-app-endpoint' => [json_encode([['name' => 'SomeService', 'label' => 'SomeService', 'host' => 'https://www.someservice.com']])];

        yield '1-valid-1-invalid' => [json_encode([
            ['name' => 'SomeService', 'label' => 'SomeService', 'host' => 'https://www.someservice.com', 'app-endpoint' => '/register'],
            ['not-valid' => 1],
        ])];
    }

    #[DataProvider('invalidResponseProvider')]
    public function testInvalidResponseBodyReturnsEmptyListOfServices(string $response): void
    {
        $client = new MockHttpClient([
            $response = new MockResponse($response),
        ]);

        $registryClient = new ServiceRegistryClient('https://www.shopware.com/services.json', $client);

        static::assertEquals([], $registryClient->getAll());
        static::assertEquals('https://www.shopware.com/services.json', $response->getRequestUrl());
    }

    public function testFailRequestReturnsEmptyListOfServices(): void
    {
        $client = new MockHttpClient([
            $response = new MockResponse('', ['http_code' => 503]),
        ]);

        $registryClient = new ServiceRegistryClient('https://www.shopware.com/services.json', $client);

        static::assertEquals([], $registryClient->getAll());
        static::assertEquals('https://www.shopware.com/services.json', $response->getRequestUrl());
    }

    public function testSuccessfulRequestReturnsListOfServices(): void
    {
        $service = [
            ['name' => 'MyCoolService1', 'host' => 'https://coolservice1.com', 'label' => 'My Cool Service 1', 'app-endpoint' => '/app-endpoint'],
            ['name' => 'MyCoolService2', 'host' => 'https://coolservice2.com', 'label' => 'My Cool Service 2', 'app-endpoint' => '/app-endpoint'],
        ];

        $client = new MockHttpClient([
            $response = new MockResponse((string) json_encode($service)),
        ]);

        $registryClient = new ServiceRegistryClient('https://www.shopware.com/services.json', $client);

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
