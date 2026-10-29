<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\OpenApi;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\RequestStackTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Framework\Api\OpenApi\Fixture\DtoFieldsRoute;
use Shopware\Tests\Integration\Core\Framework\Api\OpenApi\Fixture\DtoFieldsRouteExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class OpenApiDtoTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use RequestStackTestBehaviour;

    private static string $salesChannelAccessKey;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $accessKey = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT access_key FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL)],
        );
        static::assertIsString($accessKey, 'The default test sales channel must exist.');
        self::$salesChannelAccessKey = $accessKey;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string|null> $expected
     */
    #[DataProvider('fieldValues')]
    public function testGeneratedDtosThroughStoreApi(array $payload, array $expected, int $status = Response::HTTP_OK): void
    {
        $container = static::getContainer();

        $route = new DtoFieldsRoute($container->get(ExtensionDispatcher::class));

        $request = Request::create(
            '/store-api/test/dto-fields',
            Request::METHOD_POST,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
        $request->headers->set(PlatformRequest::HEADER_ACCESS_KEY, self::$salesChannelAccessKey);
        $request->attributes->add([
            '_controller' => $route->transform(...),
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
        ]);

        $before = null;
        $after = null;
        $extension = null;
        $hooks = EventHookDispatcher::fromContainer($container);
        $hooks->on(DtoFieldsRouteExtension::onPre(), static function (DtoFieldsRouteExtension $event) use (&$before, &$extension): void {
            $extension = $event;
            $before = get_object_vars($event->payload);
        });
        $hooks->on(DtoFieldsRouteExtension::onPost(), static function (DtoFieldsRouteExtension $event) use (&$after): void {
            $after = get_object_vars($event->payload);
        });

        $response = static::getKernel()->handle($request);
        $content = $response->getContent();
        static::assertIsString($content);
        static::assertSame($status, $response->getStatusCode(), $content);
        static::assertSame('application/json', $response->headers->get('Content-Type'));

        if ($status !== Response::HTTP_OK) {
            static::assertNull($before, 'Invalid payload must not reach the route extension.');
            static::assertNull($after);
            static::assertJson($content);

            return;
        }

        static::assertInstanceOf(DtoFieldsRouteExtension::class, $extension);
        static::assertSame($request, $extension->request);
        static::assertSame(TestDefaults::SALES_CHANNEL, $extension->context->getSalesChannelId());
        static::assertEquals($payload, $before);
        static::assertSame($before, $after, 'The route must not change request properties.');
        static::assertJsonStringEqualsJsonString(json_encode($expected, \JSON_THROW_ON_ERROR), $content);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, string|null>, 2?: int}>
     */
    public static function fieldValues(): iterable
    {
        $all = [
            'requiredScalar' => 'required',
            'requiredScalarWithDefault' => 'required default overridden',
            'requiredNullable' => 'required nullable',
            'requiredNullableWithDefault' => 'required nullable default overridden',
            'optionalScalar' => 'optional',
            'optionalScalarWithDefault' => 'optional default overridden',
            'optionalNullable' => 'optional nullable',
            'optionalNullableWithDefault' => 'optional nullable default overridden',
        ];
        $transformed = [
            'requiredScalar' => 'REQUIRED',
            'requiredScalarWithDefault' => 'REQUIRED DEFAULT OVERRIDDEN',
            'requiredNullable' => 'REQUIRED NULLABLE',
            'requiredNullableWithDefault' => 'REQUIRED NULLABLE DEFAULT OVERRIDDEN',
            'optionalScalar' => 'OPTIONAL',
            'optionalScalarWithDefault' => 'OPTIONAL DEFAULT OVERRIDDEN',
            'optionalNullable' => 'OPTIONAL NULLABLE',
            'optionalNullableWithDefault' => 'OPTIONAL NULLABLE DEFAULT OVERRIDDEN',
        ];

        yield 'all eight fields are mapped, changed and serialized' => [$all, $transformed];

        $required = \array_slice($all, 0, 4);
        yield 'business defaults fill omitted optional fields with schema defaults' => [
            $required,
            [...\array_slice($transformed, 0, 4), 'optionalScalarWithDefault' => 'SCHEMA-DEFAULT', 'optionalNullableWithDefault' => null],
        ];

        foreach (['requiredNullable', 'requiredNullableWithDefault', 'optionalNullable', 'optionalNullableWithDefault'] as $field) {
            yield $field . ' preserves explicit null' => [
                array_replace($all, [$field => null]),
                array_replace($transformed, [$field => null]),
            ];
        }

        foreach (['optionalScalar', 'optionalNullable'] as $field) {
            $payload = $all;
            $expected = $transformed;
            unset($payload[$field], $expected[$field]);
            yield $field . ' preserves omission' => [$payload, $expected];
        }

        $withoutScalarDefault = $all;
        unset($withoutScalarDefault['optionalScalarWithDefault']);
        yield 'omitted optional scalar uses its default constant before conversion' => [
            $withoutScalarDefault,
            array_replace($transformed, ['optionalScalarWithDefault' => 'SCHEMA-DEFAULT']),
        ];

        $withoutNullableDefault = $all;
        unset($withoutNullableDefault['optionalNullableWithDefault']);
        yield 'omitted optional nullable uses its null default constant' => [
            $withoutNullableDefault,
            array_replace($transformed, ['optionalNullableWithDefault' => null]),
        ];

        yield 'empty strings stay values' => [array_fill_keys(array_keys($all), ''), array_fill_keys(array_keys($all), '')];

        foreach (array_keys($required) as $field) {
            $payload = $all;
            unset($payload[$field]);
            yield $field . ' remains mandatory even with a default or nullable type' => [$payload, [], Response::HTTP_UNPROCESSABLE_ENTITY];
        }

        foreach (['requiredScalar', 'requiredScalarWithDefault', 'optionalScalar', 'optionalScalarWithDefault'] as $field) {
            yield $field . ' rejects null' => [array_replace($all, [$field => null]), [], Response::HTTP_UNPROCESSABLE_ENTITY];
        }

        foreach (array_keys($all) as $field) {
            yield $field . ' rejects an object instead of a scalar' => [array_replace($all, [$field => ['invalid' => true]]), [], Response::HTTP_UNPROCESSABLE_ENTITY];
        }
    }
}
