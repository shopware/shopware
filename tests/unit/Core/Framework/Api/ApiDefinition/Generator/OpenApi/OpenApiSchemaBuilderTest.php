<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\OpenApi;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OpenApiSchemaBuilder::class)]
class OpenApiSchemaBuilderTest extends TestCase
{
    use EnvTestBehaviour;

    public function testCreateSpecAddsDefaultErrorResponsesForStoreApi(): void
    {
        $spec = (new OpenApiSchemaBuilder('6.7.0.0'))->createSpec(DefinitionService::STORE_API);

        $responses = $spec['components']['responses'];

        foreach ([
            Response::HTTP_BAD_REQUEST => 'Bad Request',
            Response::HTTP_UNAUTHORIZED => 'Unauthorized',
            Response::HTTP_FORBIDDEN => 'Forbidden',
            Response::HTTP_NOT_FOUND => 'Not Found',
            Response::HTTP_TOO_MANY_REQUESTS => 'Too Many Requests',
        ] as $statusCode => $description) {
            static::assertArrayHasKey($statusCode, $responses, \sprintf('Default response for status %d is missing', $statusCode));
            static::assertIsArray($responses[$statusCode]);
            static::assertSame($description, $responses[$statusCode]['description']);
            static::assertSame(
                ['$ref' => '#/components/schemas/failure'],
                $responses[$statusCode]['content']['application/json']['schema']
            );
        }

        static::assertArrayNotHasKey(Response::HTTP_NO_CONTENT, $responses);
    }

    public function testCreateSpecAddsNoContentDefaultResponseForAdminApi(): void
    {
        $spec = (new OpenApiSchemaBuilder('6.7.0.0'))->createSpec(DefinitionService::API);

        $responses = $spec['components']['responses'];

        static::assertArrayHasKey(Response::HTTP_NO_CONTENT, $responses);
        static::assertSame(['description' => 'No Content'], $responses[Response::HTTP_NO_CONTENT]);
    }

    public function testCreateSpecUsesApiKeySecurityForStoreApi(): void
    {
        $spec = (new OpenApiSchemaBuilder('6.7.0.0'))->createSpec(DefinitionService::STORE_API);

        static::assertSame([['ApiKey' => []]], $spec['security']);
        static::assertSame(['ApiKey', 'ContextToken'], array_keys($spec['components']['securitySchemes']));
        static::assertSame('Shopware Store API', $spec['info']['title']);
        static::assertSame('6.7.0.0', $spec['info']['version']);
        static::assertSame(OpenApiSchemaBuilder::OPENAPI_VERSION, $spec['openapi']);
        static::assertSame([], $spec['paths']);
    }

    public function testCreateSpecUsesOAuthSecurityForAdminApi(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://shop.example']);

        $spec = (new OpenApiSchemaBuilder('6.7.0.0'))->createSpec(DefinitionService::API);

        static::assertSame([['oAuth' => ['write']]], $spec['security']);
        static::assertSame('Shopware Admin API', $spec['info']['title']);
        static::assertSame(
            'https://shop.example/api/oauth/token',
            $spec['components']['securitySchemes']['oAuth']['flows']['password']['tokenUrl']
        );
    }

    #[DataProvider('serverUrlProvider')]
    public function testServerUrl(string $api, string $appUrl, string $appEnv, string $expectedUrl): void
    {
        $this->setEnvVars([
            'APP_ENV' => $appEnv,
            'APP_URL' => $appUrl,
        ]);

        $spec = (new OpenApiSchemaBuilder('6.7.0.0'))->createSpec($api);

        static::assertSame([['url' => $expectedUrl]], $spec['servers']);
    }

    public static function serverUrlProvider(): \Generator
    {
        yield 'store api uses relative url for localhost in production' => [
            DefinitionService::STORE_API,
            'http://localhost:8000',
            'prod',
            '/store-api',
        ];

        yield 'admin api uses relative url for localhost in production' => [
            DefinitionService::API,
            'http://localhost:8000',
            'prod',
            '/api',
        ];

        yield 'store api uses configured app url for public url in production' => [
            DefinitionService::STORE_API,
            'https://shop.example',
            'prod',
            'https://shop.example/store-api',
        ];

        yield 'admin api uses configured app url for public url in production' => [
            DefinitionService::API,
            'https://shop.example',
            'prod',
            'https://shop.example/api',
        ];

        yield 'store api uses configured localhost app url outside production' => [
            DefinitionService::STORE_API,
            'http://localhost:8000',
            'dev',
            'http://localhost:8000/store-api',
        ];

        yield 'admin api uses configured localhost app url outside production' => [
            DefinitionService::API,
            'http://localhost:8000',
            'dev',
            'http://localhost:8000/api',
        ];
    }

    public function testCreateSpecAddsRelationshipSchemasForAdminApi(): void
    {
        $spec = (new OpenApiSchemaBuilder('6.7.0.0'))->createSpec(DefinitionService::API);

        $schema = $spec['components']['schemas'];

        static::assertSame(
            ['$ref' => '#/components/schemas/relationship'],
            $schema['relationships']['additionalProperties']
        );
        static::assertEqualsCanonicalizing(['data', 'meta', 'links'], array_keys($schema['relationship']['properties']));
        static::assertSame(1, $schema['relationship']['minProperties']);
        static::assertFalse($schema['relationship']['additionalProperties']);
        static::assertArrayNotHasKey('anyOf', $schema['relationship']);
    }

    public function testCreateSpecDoesNotAddDefaultSchemasForStoreApi(): void
    {
        $spec = (new OpenApiSchemaBuilder('6.7.0.0'))->createSpec(DefinitionService::STORE_API);

        static::assertArrayNotHasKey('schemas', $spec['components']);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with OpenApiSchemaBuilder::enrich()
     */
    #[DataProvider('apiProvider')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testEnrichSerializesToTheCreatedSpec(string $api): void
    {
        $this->setEnvVars(['APP_URL' => 'https://shop.example']);
        $builder = new OpenApiSchemaBuilder('6.7.0.0');

        $openApi = new OpenApi(['openapi' => OpenApiSchemaBuilder::OPENAPI_VERSION]);
        $builder->enrich($openApi, $api);

        $expected = $builder->createSpec($api);
        // swagger-php leaves the empty path map out of the document
        unset($expected['paths']);

        static::assertSame($expected, json_decode($openApi->toJson(), true, flags: \JSON_THROW_ON_ERROR));
    }

    public static function apiProvider(): \Generator
    {
        yield 'admin api' => [DefinitionService::API];
        yield 'store api' => [DefinitionService::STORE_API];
    }
}
