<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\ServiceRegistry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Deployment\AirGappedMode;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\Service\ServiceRegistry\Client as ServiceRegistryClient;
use Shopware\Core\Service\ServiceRegistry\SaveConsentRequest;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceRegistryClient::class)]
class ClientTest extends TestCase
{
    public static function invalidResponseProvider(): \Generator
    {
        yield 'not-json' => [''];

        yield 'no-services-key' => [json_encode(['blah' => [1, 2, 3]], \JSON_THROW_ON_ERROR)];

        yield 'not-correct-list' => [json_encode(['services' => [1, 2, 3]], \JSON_THROW_ON_ERROR)];

        yield 'not-correct-service-definition' => [json_encode(['services' => [['not-valid' => 1]]], \JSON_THROW_ON_ERROR)];

        yield 'missing-label' => [json_encode(['services' => [['name' => 'SomeService']]], \JSON_THROW_ON_ERROR)];

        yield 'missing-host' => [json_encode(['services' => [['name' => 'SomeService', 'label' => 'SomeService']]], \JSON_THROW_ON_ERROR)];

        yield 'missing-app-endpoint' => [
            json_encode([
                'services' => [
                    [
                        'name' => 'SomeService',
                        'label' => 'SomeService',
                        'host' => 'https://www.someservice.com',
                    ],
                ],
            ], \JSON_THROW_ON_ERROR),
        ];

        yield '1-valid-1-invalid' => [
            json_encode([
                'services' => [
                    ['name' => 'SomeService', 'label' => 'SomeService', 'host' => 'https://www.someservice.com', 'app-endpoint' => '/register'],
                    ['not-valid' => 1],
                ],
            ], \JSON_THROW_ON_ERROR),
        ];
    }

    #[DataProvider('invalidResponseProvider')]
    public function testInvalidResponseBodyReturnsEmptyListOfServices(string $response): void
    {
        $client = new MockHttpClient([
            $response = new MockResponse($response),
        ]);

        $registryClient = new ServiceRegistryClient('https://www.shopware.com', 'https://example.com', $client, new AirGappedMode(false));

        static::assertSame([], $registryClient->getAll());
        static::assertSame('https://www.shopware.com/api/service/?page=1&limit=10', $response->getRequestUrl());
    }

    public function testFailRequestReturnsEmptyListOfServices(): void
    {
        $client = new MockHttpClient([
            $response = new MockResponse('', ['http_code' => Response::HTTP_SERVICE_UNAVAILABLE]),
        ]);

        $registryClient = new ServiceRegistryClient('https://www.shopware.com', 'https://example.com', $client, new AirGappedMode(false));

        static::assertSame([], $registryClient->getAll());
        static::assertSame('https://www.shopware.com/api/service/?page=1&limit=10', $response->getRequestUrl());
    }

    public function testSuccessfulRequestReturnsListOfServices(): void
    {
        $service = [
            'services' => [
                ['name' => 'MyCoolService1', 'host' => 'https://coolservice1.com', 'label' => 'My Cool Service 1', 'app-endpoint' => '/app-endpoint'],
                ['name' => 'MyCoolService2', 'host' => 'https://coolservice2.com', 'label' => 'My Cool Service 2', 'app-endpoint' => '/app-endpoint'],
            ],
        ];

        $client = new MockHttpClient([
            $response = new MockResponse((string) json_encode($service, \JSON_THROW_ON_ERROR)),
        ]);

        $registryClient = new ServiceRegistryClient('https://www.shopware.com', 'https://example.com', $client, new AirGappedMode(false));

        $entries = $registryClient->getAll();

        static::assertCount(2, $entries);
        static::assertContainsOnlyInstancesOf(ServiceEntry::class, $entries);
        static::assertSame('MyCoolService1', $entries[0]->name);
        static::assertSame('My Cool Service 1', $entries[0]->description);
        static::assertSame('https://coolservice1.com', $entries[0]->host);
        static::assertSame('/app-endpoint', $entries[0]->appEndpoint);
        static::assertSame('MyCoolService2', $entries[1]->name);
        static::assertSame('My Cool Service 2', $entries[1]->description);
        static::assertSame('https://coolservice2.com', $entries[1]->host);
        static::assertSame('/app-endpoint', $entries[1]->appEndpoint);
        static::assertSame('https://www.shopware.com/api/service/?page=1&limit=10', $response->getRequestUrl());
    }

    public function testGetAllReturnsEmptyListWhenAirGapped(): void
    {
        $httpClient = new MockHttpClient([]);

        $registryClient = new ServiceRegistryClient(
            'https://www.shopware.com',
            'https://example.com',
            $httpClient,
            new AirGappedMode(true),
        );

        static::assertSame([], $registryClient->getAll());
        static::assertSame(0, $httpClient->getRequestsCount());
    }

    public function testSaveConsentIsNoOpWhenAirGapped(): void
    {
        $httpClient = new MockHttpClient([]);

        $registryClient = new ServiceRegistryClient(
            'https://example.com',
            'https://example.com',
            $httpClient,
            new AirGappedMode(true),
        );

        $registryClient->saveConsent(new SaveConsentRequest(
            'service-123',
            'user-456',
            'shop-789',
            '2023-07-01T10:00:00Z',
            'v1.0',
        ));

        static::assertSame(0, $httpClient->getRequestsCount());
    }

    public function testRevokeConsentIsNoOpWhenAirGapped(): void
    {
        $httpClient = new MockHttpClient([]);

        $registryClient = new ServiceRegistryClient(
            'https://example.com',
            'https://example.com',
            $httpClient,
            new AirGappedMode(true),
        );

        $registryClient->revokeConsent('service-123');

        static::assertSame(0, $httpClient->getRequestsCount());
    }

    public function testFetchServiceZipThrowsWhenAirGapped(): void
    {
        $httpClient = new MockHttpClient([]);

        $registryClient = new ServiceRegistryClient(
            'https://example.com',
            'https://example.com',
            $httpClient,
            new AirGappedMode(true),
        );

        $this->expectExceptionObject(FrameworkException::airGapped());

        iterator_to_array($registryClient->fetchServiceZip('https://example.com/service.zip'));
    }

    public function testServicesAreFetchedAndCached(): void
    {
        $service = [
            'services' => [
                ['name' => 'MyCoolService1', 'host' => 'https://coolservice1.com', 'label' => 'My Cool Service 1', 'app-endpoint' => '/app-endpoint'],
                ['name' => 'MyCoolService2', 'host' => 'https://coolservice2.com', 'label' => 'My Cool Service 2', 'app-endpoint' => '/app-endpoint'],
            ],
        ];

        $client = new MockHttpClient([
            new MockResponse((string) json_encode($service, \JSON_THROW_ON_ERROR)),
        ]);

        $registryClient = new ServiceRegistryClient('https://www.shopware.com', 'https://example.com', $client, new AirGappedMode(false));

        $entries1 = $registryClient->getAll();
        static::assertCount(2, $entries1);

        // second fetch would be empty array, since request would fail as we don't provide a second mocked response.
        // registry client will catch and return empty array.
        $entries2 = $registryClient->getAll();
        static::assertCount(2, $entries2);

        static::assertSame($entries1, $entries2);
    }

    public function testResetCausesRefetch(): void
    {
        $services1 = [
            'services' => [
                ['name' => 'MyCoolService1', 'host' => 'https://coolservice1.com', 'label' => 'My Cool Service 1', 'app-endpoint' => '/app-endpoint'],
                ['name' => 'MyCoolService2', 'host' => 'https://coolservice2.com', 'label' => 'My Cool Service 2', 'app-endpoint' => '/app-endpoint'],
            ],
        ];

        $services2 = [
            'services' => [
                ['name' => 'MyCoolService1', 'host' => 'https://coolservice1.com', 'label' => 'My Cool Service 1', 'app-endpoint' => '/app-endpoint'],
                ['name' => 'MyCoolService2', 'host' => 'https://coolservice2.com', 'label' => 'My Cool Service 2', 'app-endpoint' => '/app-endpoint'],
                ['name' => 'MyCoolService3', 'host' => 'https://coolservice3.com', 'label' => 'My Cool Service 3', 'app-endpoint' => '/app-endpoint'],
            ],
        ];

        $client = new MockHttpClient([
            new MockResponse((string) json_encode($services1, \JSON_THROW_ON_ERROR)),
            new MockResponse((string) json_encode($services2, \JSON_THROW_ON_ERROR)),
        ]);

        $registryClient = new ServiceRegistryClient('https://www.shopware.com', 'https://example.com', $client, new AirGappedMode(false));

        $entries1 = $registryClient->getAll();
        static::assertCount(2, $entries1);

        $registryClient->reset();

        $entries2 = $registryClient->getAll();
        static::assertCount(3, $entries2);
    }

    public function testPaginationWorks(): void
    {
        $servicesPage1 = [
            'services' => [['name' => 'MyCoolService1', 'host' => 'https://coolservice1.com', 'label' => 'My Cool Service 1', 'app-endpoint' => '/app-endpoint']],
            'pagination' => ['page' => 1, 'pages' => 2, 'total' => 2, 'limit' => 10],
        ];

        $servicesPage2 = [
            'services' => [['name' => 'MyCoolService2', 'host' => 'https://coolservice2.com', 'label' => 'My Cool Service 2', 'app-endpoint' => '/app-endpoint']],
            'pagination' => ['page' => 2, 'pages' => 2, 'total' => 2, 'limit' => 10],
        ];

        $client = new MockHttpClient([
            $response1 = new MockResponse((string) json_encode($servicesPage1, \JSON_THROW_ON_ERROR)),
            $response2 = new MockResponse((string) json_encode($servicesPage2, \JSON_THROW_ON_ERROR)),
        ]);

        $registryClient = new ServiceRegistryClient('https://www.shopware.com/', 'https://example.com', $client, new AirGappedMode(false));
        $entries = $registryClient->getAll();

        static::assertCount(2, $entries);
        static::assertSame('MyCoolService1', $entries[0]->name);
        static::assertSame('My Cool Service 1', $entries[0]->description);
        static::assertSame('https://coolservice1.com', $entries[0]->host);
        static::assertSame('/app-endpoint', $entries[0]->appEndpoint);
        static::assertSame('MyCoolService2', $entries[1]->name);
        static::assertSame('My Cool Service 2', $entries[1]->description);
        static::assertSame('https://coolservice2.com', $entries[1]->host);
        static::assertSame('/app-endpoint', $entries[1]->appEndpoint);
        static::assertSame('https://www.shopware.com/api/service/?page=1&limit=10', $response1->getRequestUrl());
        static::assertSame('https://www.shopware.com/api/service/?page=2&limit=10', $response2->getRequestUrl());
    }

    public function testNetworkExceptionReturnsEmptyListOfServices(): void
    {
        $client = new MockHttpClient([
            static function (): void {
                throw new TransportException('Network error');
            },
        ]);

        $registryClient = new ServiceRegistryClient('https://example.com', 'https://example.com', $client, new AirGappedMode(false));

        static::assertSame([], $registryClient->getAll());
    }

    public function testSaveConsentSuccess(): void
    {
        $client = new MockHttpClient([
            $response = new MockResponse('', ['http_code' => Response::HTTP_ACCEPTED]), // Changed from 200 to 202 (HTTP_ACCEPTED)
        ]);

        $registryClient = new ServiceRegistryClient('https://example.com', 'https://example.com', $client, new AirGappedMode(false));

        $saveConsentRequest = new SaveConsentRequest(
            'service-123',
            'user-456',
            'shop-789',
            '2023-07-01T10:00:00Z',
            'v1.0',
            'https://license.example.com'
        );

        $registryClient->saveConsent($saveConsentRequest);

        static::assertSame('https://example.com/api/consent/', $response->getRequestUrl());
        static::assertSame('POST', $response->getRequestMethod());
    }

    public function testSaveConsentThrowsExceptionOnFailure(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => Response::HTTP_INTERNAL_SERVER_ERROR]),
        ]);

        $registryClient = new ServiceRegistryClient('https://example.com', 'https://example.com', $client, new AirGappedMode(false));

        $saveConsentRequest = new SaveConsentRequest(
            'service-123',
            'user-456',
            'shop-789',
            '2023-07-01T10:00:00Z',
            'v1.0'
        );

        $this->expectException(ServiceException::class);
        $registryClient->saveConsent($saveConsentRequest);
    }

    public function testSaveConsentThrowsExceptionOnNonAcceptedStatusCode(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => Response::HTTP_OK]), // 200 is not HTTP_ACCEPTED (202)
        ]);

        $registryClient = new ServiceRegistryClient('https://example.com', 'https://example.com', $client, new AirGappedMode(false));

        $saveConsentRequest = new SaveConsentRequest(
            'service-123',
            'user-456',
            'shop-789',
            '2023-07-01T10:00:00Z',
            'v1.0'
        );

        $this->expectExceptionObject(ServiceException::consentSaveFailed('Unexpected response status code: 200'));
        $registryClient->saveConsent($saveConsentRequest);
    }

    public function testRevokeConsentSuccess(): void
    {
        $client = new MockHttpClient([
            $response = new MockResponse('', ['http_code' => Response::HTTP_ACCEPTED]), // Changed from 200 to 202 (HTTP_ACCEPTED)
        ]);

        $registryClient = new ServiceRegistryClient('https://example.com', 'https://example.com', $client, new AirGappedMode(false));

        $registryClient->revokeConsent('service-123');

        static::assertSame('https://example.com/api/consent/revoke/service-123', $response->getRequestUrl());
        static::assertSame('DELETE', $response->getRequestMethod());
    }

    public function testRevokeConsentThrowsExceptionOnFailure(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => Response::HTTP_INTERNAL_SERVER_ERROR]),
        ]);

        $registryClient = new ServiceRegistryClient('https://example.com', 'https://example.com', $client, new AirGappedMode(false));

        $this->expectExceptionObject(ServiceException::consentRevokeFailed('Unexpected response status code: 500'));
        $registryClient->revokeConsent('service-123');
    }

    public function testRevokeConsentThrowsExceptionOnNonAcceptedStatusCode(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => Response::HTTP_OK]), // 200 is not HTTP_ACCEPTED (202)
        ]);

        $registryClient = new ServiceRegistryClient('https://example.com', 'https://example.com', $client, new AirGappedMode(false));

        $this->expectExceptionObject(ServiceException::consentRevokeFailed('Unexpected response status code: 200'));
        $registryClient->revokeConsent('service-123');
    }

    public function testExtraBackslashDoesntBreakApp(): void
    {
        $service = [
            'services' => [
                ['name' => 'MyCoolService1', 'host' => 'https://coolservice1.com', 'label' => 'My Cool Service 1', 'app-endpoint' => '/app-endpoint'],
            ],
        ];
        $servicePayload = (string) json_encode($service, \JSON_THROW_ON_ERROR);
        $expectedUrl = 'https://www.shopware.com/api/service/?page=1&limit=10';
        $clientWithSlash = new MockHttpClient([$responseWithSlash = new MockResponse($servicePayload)]);
        $registryClientWithSlash = new ServiceRegistryClient('https://www.shopware.com/', 'https://example.com', $clientWithSlash, new AirGappedMode(false));
        $entriesWithSlash = $registryClientWithSlash->getAll();
        static::assertCount(1, $entriesWithSlash);
        static::assertSame($expectedUrl, $responseWithSlash->getRequestUrl());

        $clientWithoutSlash = new MockHttpClient([$responseWithoutSlash = new MockResponse($servicePayload)]);
        $registryClientWithoutSlash = new ServiceRegistryClient('https://www.shopware.com', 'https://example.com', $clientWithoutSlash, new AirGappedMode(false));
        $entriesWithoutSlash = $registryClientWithoutSlash->getAll();
        static::assertCount(1, $entriesWithoutSlash);
        static::assertSame($expectedUrl, $responseWithoutSlash->getRequestUrl());
    }

    public function testFetchServiceZipStreamsChunks(): void
    {
        $zipUrl = 'https://files.example.com/service.zip';
        $payload = random_bytes(128);

        $client = new MockHttpClient([
            $response = new MockResponse($payload, [
                'response_headers' => ['Content-Type: application/zip'],
            ]),
        ]);

        $registryClient = new ServiceRegistryClient('https://example.com', 'https://example.com', $client, new AirGappedMode(false));

        $collected = '';
        foreach ($registryClient->fetchServiceZip($zipUrl) as $chunk) {
            $collected .= $chunk->getContent();
        }

        static::assertSame($payload, $collected);
        static::assertSame($zipUrl, $response->getRequestUrl());
        static::assertSame('GET', $response->getRequestMethod());
    }

    public function testFetchServiceZipThrowsOnNon200(): void
    {
        $zipUrl = 'https://files.example.com/missing.zip';

        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => Response::HTTP_NOT_FOUND]),
        ]);

        $registryClient = new ServiceRegistryClient('https://example.com', 'https://example.com', $client, new AirGappedMode(false));

        $this->expectException(ServiceException::class);
        // Force execution so checkResponse() runs and throws
        \iterator_to_array($registryClient->fetchServiceZip($zipUrl));
    }

    public static function nonZipContentTypeProvider(): \Generator
    {
        yield 'json' => ['application/json', json_encode(['ok' => true], \JSON_THROW_ON_ERROR)];
        yield 'html' => ['text/html; charset=UTF-8', '<html><body>Not a zip</body></html>'];
    }

    #[DataProvider('nonZipContentTypeProvider')]
    public function testFetchServiceZipThrowsWhenResponseIsNotZip(string $contentType, string $body): void
    {
        $zipUrl = 'https://files.example.com/service.zip';

        $client = new MockHttpClient([
            new MockResponse($body, [
                'http_code' => Response::HTTP_OK,
                'response_headers' => ['Content-Type: ' . $contentType],
            ]),
        ]);

        $registryClient = new ServiceRegistryClient('https://example.com', 'https://example.com', $client, new AirGappedMode(false));
        $this->expectException(ServiceException::class);
        // Force execution so checkResponse() runs and throws
        \iterator_to_array($registryClient->fetchServiceZip($zipUrl));
    }

    public function testRefererHeaderIsAddedToServiceListRequest(): void
    {
        $service = [
            'services' => [],
        ];

        $client = new MockHttpClient([
            $response = new MockResponse((string) json_encode($service, \JSON_THROW_ON_ERROR)),
        ]);

        $registryClient = new ServiceRegistryClient('https://www.shopware.com', 'https://example.com', $client, new AirGappedMode(false));

        $registryClient->getAll();

        $requestOptions = $response->getRequestOptions();
        static::assertArrayHasKey('headers', $requestOptions);
        static::assertContains('Referer: https://example.com', $requestOptions['headers']);
    }
}
