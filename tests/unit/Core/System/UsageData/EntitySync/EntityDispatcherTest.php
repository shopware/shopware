<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\EntitySync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\EntitySync\EntityDispatcher;
use Shopware\Core\System\UsageData\EntitySync\Operation;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(EntityDispatcher::class)]
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
            static::assertContains('Shopware-Shop-Id: shop-id', $headers);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            new InstanceService('6.5.3.0', null),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
            true,
        );

        $entityDispatcher->dispatch(
            'product',
            [['id' => 'product-id']],
            Operation::DELETE,
            new \DateTimeImmutable(),
            'shop-id',
        );
    }

    public function testAddsContentEncodingHeader(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $headers = array_values($options['headers']);

            static::assertContains('Content-Encoding: gzip', $headers);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            new InstanceService('6.5.3.0', null),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
            true,
        );

        $entityDispatcher->dispatch(
            'product',
            [['id' => 'product-id']],
            Operation::DELETE,
            new \DateTimeImmutable(),
            'shop-id',
        );
    }

    public function testAddsShopIdToPayload(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('shop_id', $payload);
            static::assertSame('shop-id', $payload['shop_id']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            new InstanceService('6.5.3.0', null),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
            true,
        );

        $entityDispatcher->dispatch(
            'product',
            [['id' => 'product-id']],
            Operation::DELETE,
            new \DateTimeImmutable(),
            'shop-id',
        );
    }

    public function testAddsContentTypeHeader(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $headers = array_values($options['headers']);

            static::assertContains('Content-Type: application/json', $headers);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            new InstanceService('6.5.3.0', null),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
            true,
        );

        $entityDispatcher->dispatch(
            'product',
            [['id' => 'product-id']],
            Operation::DELETE,
            new \DateTimeImmutable(),
            'shop-id',
        );
    }

    public function testAddsEntitiesToPayload(): void
    {
        $entities = [['name' => 'entity-a'], ['name' => 'entity-b']];

        $client = new MockHttpClient(function ($method, $url, $options) use ($entities): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);

            static::assertSame(Request::METHOD_POST, $method);
            static::assertStringEndsWith('/v1/entities', $url);

            static::assertArrayHasKey('operation', $payload);
            static::assertSame(Operation::CREATE->value, $payload['operation']);

            static::assertArrayHasKey('entity', $payload);
            static::assertSame('product', $payload['entity']);

            static::assertArrayHasKey('entities', $payload);
            static::assertSame($entities, $payload['entities']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            new InstanceService('6.5.3.0', null),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
            true,
        );

        $entityDispatcher->dispatch(
            'product',
            $entities,
            Operation::CREATE,
            new \DateTimeImmutable(),
            'shop-id',
        );
    }

    public function testAddsOperationToPayload(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            static::assertSame(Request::METHOD_POST, $method);
            static::assertStringEndsWith('/v1/entities', $url);

            static::assertArrayHasKey('operation', $payload);
            static::assertSame(Operation::CREATE->value, $payload['operation']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            new InstanceService('6.5.3.0', null),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
            true,
        );

        $entityDispatcher->dispatch(
            'product',
            [['id' => 'product-id']],
            Operation::CREATE,
            new \DateTimeImmutable(),
            'shop-id',
        );
    }

    public function testAddsShopwareVersionToPayload(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('shopware_version', $payload);
            static::assertSame('6.5.3.0', $payload['shopware_version']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            new InstanceService('6.5.3.0', null),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
            true,
        );

        $entityDispatcher->dispatch(
            'product',
            [['id' => 'product-id']],
            Operation::CREATE,
            new \DateTimeImmutable(),
            'shop-id',
        );
    }

    public function testAddsEnvironmentToPayload(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('environment', $payload);
            static::assertSame('prod', $payload['environment']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            new InstanceService('6.5.3.0', null),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
            true,
        );

        $entityDispatcher->dispatch(
            'product',
            [['id' => 'product-id']],
            Operation::CREATE,
            new \DateTimeImmutable(),
            'shop-id',
        );
    }

    public function testAddsRunDateToPayload(): void
    {
        $runDate = new \DateTimeImmutable();

        $client = new MockHttpClient(function ($method, $url, $options) use ($runDate): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('run_date', $payload);
            static::assertSame($runDate->format(Defaults::STORAGE_DATE_TIME_FORMAT), $payload['run_date']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            new InstanceService('6.5.3.0', null),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
            true,
        );

        $entityDispatcher->dispatch(
            'product',
            [['id' => 'product-id']],
            Operation::CREATE,
            $runDate,
            'shop-id',
        );
    }

    public function testAddsDispatchDateToPayload(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('dispatch_date', $payload);
            static::assertSame($this->clock->now()->format(\DateTimeInterface::ATOM), $payload['dispatch_date']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            new InstanceService('6.5.3.0', null),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
            true,
        );

        $runDate = new \DateTimeImmutable();

        $entityDispatcher->dispatch(
            'product',
            [['id' => 'product-id']],
            Operation::CREATE,
            $runDate,
            'shop-id',
        );
    }

    public function testAddsLicenseHostToPayload(): void
    {
        $client = new MockHttpClient(function ($method, $url, $options): MockResponse {
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('license_host', $payload);
            static::assertSame('license-host', $payload['license_host']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            new InstanceService('6.5.3.0', null),
            new StaticSystemConfigService(['core.store.licenseHost' => 'license-host']),
            $this->clock,
            'prod',
            true,
        );

        $runDate = new \DateTimeImmutable();

        $entityDispatcher->dispatch(
            'product',
            [['id' => 'product-id']],
            Operation::CREATE,
            $runDate,
            'shop-id',
        );
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
            new InstanceService('6.5.3.0', null),
            new StaticSystemConfigService(['core.store.licenseHost' => 'license-host']),
            $this->clock,
            'prod',
            true,
        );

        $runDate = new \DateTimeImmutable();

        $entityDispatcher->dispatch(
            'product',
            [['id' => 'product-id']],
            Operation::CREATE,
            $runDate,
            'shop-id',
        );
    }

    public function testItThrowsExceptionsWhichMightBeRecoverable(): void
    {
        $client = new MockHttpClient(function (): MockResponse {
            return new MockResponse('', ['http_code' => 300]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            new InstanceService('6.5.3.0', null),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
            true,
        );

        $runDate = new \DateTimeImmutable();

        static::expectException(RedirectionException::class);
        $entityDispatcher->dispatch(
            'product',
            [['id' => 'product-id']],
            Operation::CREATE,
            $runDate,
            'shop-id',
        );
    }

    #[DataProvider('recoverableResponseCodesDataProvider')]
    public function testItThrowsRecoverableServerException(int $responseCode): void
    {
        $client = new MockHttpClient(function () use ($responseCode): MockResponse {
            return new MockResponse('', ['http_code' => $responseCode]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            new InstanceService('6.5.3.0', null),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
            true,
        );

        $runDate = new \DateTimeImmutable();

        static::expectException(ServerException::class);
        $entityDispatcher->dispatch(
            'product',
            [['id' => 'product-id']],
            Operation::CREATE,
            $runDate,
            'shop-id',
        );
    }

    #[DataProvider('unrecoverableResponseCodesDataProvider')]
    public function testItThrowsUnrecoverableMessageHandlingException(int $responseCode): void
    {
        $client = new MockHttpClient(function () use ($responseCode): MockResponse {
            return new MockResponse('', ['http_code' => $responseCode]);
        });

        $entityDispatcher = new EntityDispatcher(
            $client,
            new InstanceService('6.5.3.0', null),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
            true,
        );

        $runDate = new \DateTimeImmutable();

        static::expectException(UnrecoverableMessageHandlingException::class);
        $entityDispatcher->dispatch(
            'product',
            [['id' => 'product-id']],
            Operation::CREATE,
            $runDate,
            'shop-id',
        );
    }

    public function testDispatchDoesNotSendRequestInDevEnvironment(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(static::never())->method('request');

        $entityDispatcher = new EntityDispatcher(
            $client,
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(),
            $this->clock,
            'dev',
            false,
        );

        $runDate = new \DateTimeImmutable();

        $entityDispatcher->dispatch(
            'product',
            [['field' => 'value']],
            Operation::CREATE,
            $runDate,
            'shop-id',
        );
    }

    public function testDispatchSkipsIfNoEntitiesAreGiven(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(static::never())->method('request');

        $entityDispatcher = new EntityDispatcher(
            $client,
            $this->createMock(InstanceService::class),
            new StaticSystemConfigService(),
            $this->clock,
            'prod',
            true,
        );

        $runDate = new \DateTimeImmutable();

        $entityDispatcher->dispatch(
            'product',
            [], // no entities
            Operation::CREATE,
            $runDate,
            'shop-id',
        );
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
