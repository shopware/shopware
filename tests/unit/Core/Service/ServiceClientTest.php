<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Service\ServiceClient;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[CoversClass(ServiceClient::class)]
class ServiceClientTest extends TestCase
{
    public static function latestInfoProvider(): \Generator
    {
        yield 'app-version + app-hash' => [
            [
                'app-version' => '6.6.0.0',
                'app-hash' => 'a5b32',
            ],
            ['app-revision', 'app-zip-url', 'app-hash-algorithm', 'app-min-shop-supported-version'],
        ];

        yield 'only app-version' => [
            [
                'app-version' => '6.6.0.0',
            ],
            ['app-hash', 'app-revision', 'app-zip-url', 'app-hash-algorithm', 'app-min-shop-supported-version'],
        ];

        yield 'app-revision + app-hash' => [
            [
                'app-revision' => '6.6.0.0',
                'app-hash' => 'a5b32',
            ],
            ['app-version', 'app-zip-url', 'app-hash-algorithm', 'app-min-shop-supported-version'],
        ];

        yield 'app-revision + app-version' => [
            [
                'app-revision' => '6.6.0.0',
                'app-version' => '6.6.0.0',
            ],
            ['app-hash', 'app-zip-url', 'app-hash-algorithm', 'app-min-shop-supported-version'],
        ];

        yield 'empty' => [
            [],
            ['app-version', 'app-hash', 'app-revision', 'app-zip-url', 'app-hash-algorithm', 'app-min-shop-supported-version'],
        ];
    }

    /**
     * @param array<string, string> $response
     * @param non-empty-array<int, string> $missingFields
     */
    #[DataProvider('latestInfoProvider')]
    public function testLatestInfoThrowsExceptionWithInvalidResponse(array $response, array $missingFields): void
    {
        static::expectExceptionObject(ServiceException::missingAppVersionInformation(...$missingFields));

        $httpClient = new MockHttpClient([
            new JsonMockResponse($response),
        ]);

        $client = new ServiceClient(
            $httpClient,
            '6.6.0.0',
            new ServiceEntry('MyCoolService', 'MyCoolService', 'https://example.com', '/app-endpoint')
        );
        $client->latestAppInfo();
    }

    public function testLatestInfoThrowsExceptionWhenRequestFails(): void
    {
        $response = static::createMock(ResponseInterface::class);
        $response->expects($this->any())->method('getStatusCode')->willReturn(Response::HTTP_BAD_REQUEST);

        static::expectExceptionObject(ServiceException::requestFailed($response));

        $httpClient = new MockHttpClient([
            $response,
        ]);
        $client = new ServiceClient(
            $httpClient,
            '6.6.0.0',
            new ServiceEntry('MyCoolService', 'MyCoolService', 'https://example.com', '/app-endpoint')
        );
        $client->latestAppInfo();
    }

    public function testLatestInfoThrowsExceptionWhenTransportErrorOccurs(): void
    {
        static::expectException(ServiceException::class);
        static::expectExceptionMessage('Error performing request. Error: host unreachable');

        $httpClient = new MockHttpClient([
            new MockResponse('', ['error' => 'host unreachable']),
        ]);
        $client = new ServiceClient(
            $httpClient,
            '6.6.0.0',
            new ServiceEntry('MyCoolService', 'MyCoolService', 'https://example.com', '/app-endpoint')
        );

        $client->latestAppInfo();
    }

    public function testLatestInfo(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode([
                'app-version' => '6.6.0.0',
                'app-hash' => 'a5b32',
                'app-revision' => '6.6.0.0-a5b32',
                'app-zip-url' => 'https://example.com/service/lifecycle/app-zip/6.6.0.0',
                'app-hash-algorithm' => 'sha256',
                'app-min-shop-supported-version' => '6.6.0.0',
            ])),
        ]);
        $client = new ServiceClient(
            $httpClient,
            '6.6.0.0',
            new ServiceEntry('MyCoolService', 'MyCoolService', 'https://example.com', '/app-endpoint')
        );

        $appInfo = $client->latestAppInfo();

        static::assertSame('MyCoolService', $appInfo->name);
        static::assertSame('6.6.0.0', $appInfo->version);
        static::assertSame('a5b32', $appInfo->hash);
        static::assertSame('6.6.0.0-a5b32', $appInfo->revision);
        static::assertSame('https://example.com/service/lifecycle/app-zip/6.6.0.0', $appInfo->zipUrl);
    }
}
