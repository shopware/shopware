<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Services\ServiceClient;
use Shopware\Core\Services\ServiceRegistryEntry;
use Shopware\Core\Services\ServicesException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[CoversClass(ServiceClient::class)]
class ServiceClientTest extends TestCase
{
    public static function latestInfoProvider(): \Generator
    {
        yield [
            [
                'version' => '6.6.0.0',
                'hash' => 'a5b32',
            ],
        ];

        yield [
            [
                'version' => '6.6.0.0',
            ],
        ];

        yield [
            [
                'revision' => '6.6.0.0',
                'hash' => 'a5b32',
            ],
        ];

        yield [
            [
                'revision' => '6.6.0.0',
                'version' => '6.6.0.0',
            ],
        ];

        yield [
            [],
        ];
    }

    /**
     * @param array<string, string> $response
     */
    #[DataProvider('latestInfoProvider')]
    public function testLatestInfoThrowsExceptionWithInvalidResponse(array $response): void
    {
        static::expectExceptionObject(ServicesException::missingAppVersionInfo());

        $httpClient = new MockHttpClient([
            new JsonMockResponse($response),
        ]);

        $client = new ServiceClient(
            $httpClient,
            '6.6.0.0',
            new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/app-endpoint'),
            new Filesystem()
        );
        $client->latestAppInfo();
    }

    public function testLatestInfoThrowsExceptionWhenRequestFails(): void
    {
        static::expectExceptionObject(ServicesException::requestFailed(400));

        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 400]),
        ]);
        $client = new ServiceClient(
            $httpClient,
            '6.6.0.0',
            new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/app-endpoint'),
            new Filesystem()
        );
        $client->latestAppInfo();
    }

    public function testLatestInfoThrowsExceptionWhenTransportErrorOccurs(): void
    {
        static::expectException(ServicesException::class);
        static::expectExceptionMessage('Error performing request. Error: host unreachable');

        $httpClient = new MockHttpClient([
            new MockResponse('', ['error' => 'host unreachable']),
        ]);
        $client = new ServiceClient(
            $httpClient,
            '6.6.0.0',
            new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/app-endpoint'),
            new Filesystem(),
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
                'app-zip-url' => 'https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0',
            ])),
        ]);
        $client = new ServiceClient(
            $httpClient,
            '6.6.0.0',
            new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/app-endpoint'),
            new Filesystem(),
        );

        $appInfo = $client->latestAppInfo();

        static::assertSame('MyCoolService', $appInfo->name);
        static::assertSame('6.6.0.0', $appInfo->version);
        static::assertSame('a5b32', $appInfo->hash);
        static::assertSame('6.6.0.0-a5b32', $appInfo->revision);
        static::assertSame('https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0', $appInfo->zipUrl);
    }

    public static function appDownloadHeaderProvider(): \Generator
    {
        yield [
            [
                'sw-app-version' => '6.6.0.0',
                'sw-app-hash' => 'a5b32',
            ],
        ];

        yield [
            [
                'sw-app-version' => '6.6.0.0',
            ],
        ];

        yield [
            [
                'sw-app-revision' => '6.6.0.0',
                'sw-app-hash' => 'a5b32',
            ],
        ];

        yield [
            [
                'sw-app-revision' => '6.6.0.0',
                'sw-app-version' => '6.6.0.0',
            ],
        ];

        yield [
            [],
        ];
    }

    public function testDownloadAppZipForVersion(): void
    {
        $body = function (): \Generator {
            yield 'part1';
            yield 'part2';
            yield 'part3';
        };

        $httpClient = new MockHttpClient([
            new MockResponse($body(), [
                'response_headers' => [
                    'sw-app-version' => '6.5.0.0',
                    'sw-app-hash' => 'a5b32',
                    'sw-app-revision' => '6.5.0.0-a5b32',
                    'sw-app-zip-url' => 'https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0',
                ],
            ]),
        ]);
        $fs = static::createMock(Filesystem::class);

        $matcher = static::exactly(4);

        $fs->expects($matcher)->method('appendToFile')->willReturnCallback(function (string $filename, string $content) use ($matcher): void {
            $expectedContent = match ($matcher->numberOfInvocations()) {
                1 => 'part1',
                2 => 'part2',
                3 => 'part3',
                4 => '',
                default => null
            };

            static::assertSame('/some/file', $filename);
            static::assertSame($expectedContent, $content);
        });

        $client = new ServiceClient(
            $httpClient,
            '6.6.0.0',
            new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/app-endpoint'),
            $fs
        );

        $appInfo = $client->downloadAppZipForVersion('6.5.0.0', '/some/file');

        static::assertSame('MyCoolService', $appInfo->name);
        static::assertSame('6.5.0.0', $appInfo->version);
        static::assertSame('a5b32', $appInfo->hash);
        static::assertSame('6.5.0.0-a5b32', $appInfo->revision);
        static::assertSame('https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0', $appInfo->zipUrl);
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('appDownloadHeaderProvider')]
    public function testDownloadAppZipForVersionThrowsExceptionWithInvalidHeaders(array $headers): void
    {
        static::expectExceptionObject(ServicesException::missingAppVersionInfo());

        $httpClient = new MockHttpClient([
            new JsonMockResponse('', ['response_headers' => $headers]),
        ]);

        $client = new ServiceClient(
            $httpClient,
            '6.6.0.0',
            new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/app-endpoint'),
            new Filesystem(),
        );

        $client->downloadAppZipForVersion('https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0', '/some/file');
    }

    public function testDownloadAppZipForVersionThrowsExceptionWhenRequestFails(): void
    {
        static::expectExceptionObject(ServicesException::requestFailed(400));

        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 400]),
        ]);
        $client = new ServiceClient(
            $httpClient,
            '6.6.0.0',
            new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/app-endpoint'),
            new Filesystem(),
        );

        $client->downloadAppZipForVersion('https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0', '/some/file');
    }

    public function testDownloadAppZipForVersionThrowsExceptionWhenTransportErrorOccurs(): void
    {
        static::expectException(ServicesException::class);
        static::expectExceptionMessage('Error performing request. Error: host unreachable');

        $httpClient = new MockHttpClient([
            new MockResponse('', ['error' => 'host unreachable']),
        ]);

        $client = new ServiceClient(
            $httpClient,
            '6.6.0.0',
            new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/app-endpoint'),
            new Filesystem(),
        );

        $client->downloadAppZipForVersion('https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0', '/some/file');
    }

    public function testDownloadAppZipForVersionThrowsExceptionWhenZipCannotBeWritten(): void
    {
        static::expectExceptionObject(ServicesException::cannotWriteAppToDestination('/some/file'));

        $body = function (): \Generator {
            yield 'part1';
            yield 'part2';
            yield 'part3';
        };

        $httpClient = new MockHttpClient([
            new MockResponse($body(), [
                'response_headers' => [
                    'sw-app-version' => '6.6.0.0',
                    'sw-app-hash' => 'a5b32',
                    'sw-app-revision' => '6.6.0.0-a5b32',
                    'sw-app-zip-url' => 'https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0',
                ],
            ]),
        ]);
        $fs = static::createMock(Filesystem::class);


        $fs->method('appendToFile')->willThrowException(new IOException('blah'));

        $client = new ServiceClient(
            $httpClient,
            '6.6.0.0',
            new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/app-endpoint'),
            $fs
        );

        $client->downloadAppZipForVersion('https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0', '/some/file');
    }
}
