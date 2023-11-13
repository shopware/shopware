<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\EntitySync;

use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\EntitySync\EntityDispatcher;
use Shopware\Core\System\UsageData\EntitySync\Operation;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\EntitySync\EntityDispatcher
 */
#[Package('merchant-services')]
class EntityDispatcherTest extends TestCase
{
    private readonly ClockInterface $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2023-08-26 18:00:00.123456');
    }

    public function testAddsShopIdHeader(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $headers = array_values($options['headers']);
            static::assertContains('Shopware-Shop-Id: shop-id', array_values($headers));

            return new MockResponse('', ['http_code' => 200]);
        });

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willReturn('shop-id');

        $entityDispatcher = new EntityDispatcher(
            $client,
            $shopIdProvider,
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
        );

        $entityDispatcher->dispatch('product', [], Operation::DELETE, new \DateTimeImmutable());
    }

    public function testAddsContentEncodingHeader(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $headers = array_values($options['headers']);

            static::assertContains('Content-Encoding: gzip', array_values($headers));

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            $this->createMock(ShopIdProvider::class),
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(),
            $this->clock,
            'prod'
        );

        $entityDispatcher->dispatch('product', [], Operation::DELETE, new \DateTimeImmutable());
    }

    public function testAddsShopIdToPayload(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('shop_id', $payload);
            static::assertEquals('shop-id', $payload['shop_id']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willReturn('shop-id');

        $entityDispatcher = new EntityDispatcher(
            $client,
            $shopIdProvider,
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
        );

        $entityDispatcher->dispatch('product', [], Operation::DELETE, new \DateTimeImmutable());
    }

    public function testAddsContentTypeHeader(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $headers = array_values($options['headers']);

            static::assertContains('Content-Type: application/json', array_values($headers));

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            $this->createMock(ShopIdProvider::class),
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(),
            $this->clock,
            'prod'
        );

        $entityDispatcher->dispatch('product', [], Operation::DELETE, new \DateTimeImmutable());
    }

    public function testAddsEntitiesToPayload(): void
    {
        $entities = [['name' => 'entity-a'], ['name' => 'entity-b']];

        $client = new MockHttpClient(function ($method, $url, $options) use ($entities): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);

            static::assertEquals(Request::METHOD_POST, $method);
            static::assertStringEndsWith('/v1/entities', $url);

            static::assertArrayHasKey('operation', $payload);
            static::assertEquals(Operation::CREATE->value, $payload['operation']);

            static::assertArrayHasKey('entity', $payload);
            static::assertEquals('product', $payload['entity']);

            static::assertArrayHasKey('entities', $payload);
            static::assertEquals($entities, $payload['entities']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            $this->createMock(ShopIdProvider::class),
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
        );

        $entityDispatcher->dispatch('product', $entities, Operation::CREATE, new \DateTimeImmutable());
    }

    public function testAddsOperationToPayload(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            static::assertEquals(Request::METHOD_POST, $method);
            static::assertStringEndsWith('/v1/entities', $url);

            static::assertArrayHasKey('operation', $payload);
            static::assertEquals(Operation::CREATE->value, $payload['operation']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            $this->createMock(ShopIdProvider::class),
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
        );

        $entityDispatcher->dispatch('product', [], Operation::CREATE, new \DateTimeImmutable());
    }

    public function testAddsShopwareVersionToPayload(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('shopware_version', $payload);
            static::assertEquals('6.5.3.0', $payload['shopware_version']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $instanceService = $this->createMock(InstanceService::class);
        $instanceService->method('getShopwareVersion')
            ->willReturn('6.5.3.0');

        $httpClient = new EntityDispatcher(
            $client,
            $this->createMock(ShopIdProvider::class),
            $instanceService,
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
        );

        $httpClient->dispatch('product', [], Operation::CREATE, new \DateTimeImmutable());
    }

    public function testAddsEnvironmentToPayload(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('environment', $payload);
            static::assertEquals('prod', $payload['environment']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            $this->createMock(ShopIdProvider::class),
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
        );

        $entityDispatcher->dispatch('product', [], Operation::CREATE, new \DateTimeImmutable());
    }

    public function testAddsRunDateToPayload(): void
    {
        $runDate = new \DateTimeImmutable();

        $client = new MockHttpClient(function ($method, $url, $options) use ($runDate): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('run_date', $payload);
            static::assertEquals($runDate->format(Defaults::STORAGE_DATE_TIME_FORMAT), $payload['run_date']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            $this->createMock(ShopIdProvider::class),
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
        );

        $entityDispatcher->dispatch('product', [], Operation::CREATE, $runDate);
    }

    public function testAddsDispatchDateToPayload(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('dispatch_date', $payload);
            static::assertEquals($this->clock->now()->format(\DateTimeInterface::ATOM), $payload['dispatch_date']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            $this->createMock(ShopIdProvider::class),
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
        );

        $runDate = new \DateTimeImmutable();

        $entityDispatcher->dispatch('product', [], Operation::CREATE, $runDate);
    }

    public function testAddsLicenseHostToPayload(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('license_host', $payload);
            static::assertEquals('license-host', $payload['license_host']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            $this->createMock(ShopIdProvider::class),
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(['core.store.licenseHost' => 'license-host']),
            $this->clock,
            'prod',
        );

        $runDate = new \DateTimeImmutable();

        $entityDispatcher->dispatch('product', [], Operation::CREATE, $runDate);
    }

    public function testAddsBatchIdToPayload(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('batch_id', $payload);
            static::assertTrue(Uuid::isValid($payload['batch_id']));

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            $this->createMock(ShopIdProvider::class),
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(['core.store.licenseHost' => 'license-host']),
            $this->clock,
            'prod',
        );

        $runDate = new \DateTimeImmutable();

        $entityDispatcher->dispatch('product', [], Operation::CREATE, $runDate);
    }

    public function testItThrowsExceptionsWhichMightBeRecoverable(): void
    {
        $client = new MockHttpClient(function (): MockResponse {
            return new MockResponse('', ['http_code' => 300]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            $this->createMock(ShopIdProvider::class),
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
        );

        $runDate = new \DateTimeImmutable();

        static::expectException(RedirectionException::class);
        $entityDispatcher->dispatch('product', [], Operation::CREATE, $runDate);
    }

    /**
     * @dataProvider recoverableResponseCodesDataProvider
     */
    public function testItThrowsRecoverableServerException(int $responseCode): void
    {
        $client = new MockHttpClient(function () use ($responseCode): MockResponse {
            return new MockResponse('', ['http_code' => $responseCode]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            $this->createMock(ShopIdProvider::class),
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
        );

        $runDate = new \DateTimeImmutable();

        static::expectException(ServerException::class);
        $entityDispatcher->dispatch('product', [], Operation::CREATE, $runDate);
    }

    /**
     * @dataProvider unrecoverableResponseCodesDataProvider
     */
    public function testItThrowsUnrecoverableMessageHandlingException(int $responseCode): void
    {
        $client = new MockHttpClient(function () use ($responseCode): MockResponse {
            return new MockResponse('', ['http_code' => $responseCode]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            $this->createMock(ShopIdProvider::class),
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
        );

        $runDate = new \DateTimeImmutable();

        static::expectException(UnrecoverableMessageHandlingException::class);
        $entityDispatcher->dispatch('product', [], Operation::CREATE, $runDate);
    }

    /**
     * @return array<string, array{responseCode: int}>
     */
    public static function recoverableResponseCodesDataProvider(): array
    {
        return [
            'HTTP_BAD_GATEWAY' => [
                'responseCode' => 502,
            ],
            'HTTP_SERVICE_UNAVAILABLE' => [
                'responseCode' => 503,
            ],
            'HTTP_GATEWAY_TIMEOUT' => [
                'responseCode' => 504,
            ],
        ];
    }

    /**
     * @return array<string, array{responseCode: int}>
     */
    public static function unrecoverableResponseCodesDataProvider(): array
    {
        return [
            'HTTP_BAD_REQUEST' => [
                'responseCode' => 400,
            ],
            'HTTP_UNAUTHORIZED' => [
                'responseCode' => 401,
            ],
            'HTTP_FORBIDDEN' => [
                'responseCode' => 403,
            ],
            'HTTP_INTERNAL_SERVER_ERROR' => [
                'responseCode' => 500,
            ],
            'HTTP_VERSION_NOT_SUPPORTED' => [
                'responseCode' => 505,
            ],
        ];
    }
}
