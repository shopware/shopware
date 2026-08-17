<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\Components;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Response as OpenApiResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OpenApiSchemaBuilder::class)]
class OpenApiSchemaBuilderTest extends TestCase
{
    use EnvTestBehaviour;

    public function testEnrichAddsDefaultErrorResponsesForStoreApi(): void
    {
        $openApi = new OpenApi([]);

        (new OpenApiSchemaBuilder('6.7.0.0'))->enrich($openApi, DefinitionService::STORE_API);

        $responses = $this->getResponsesByStatusCode($openApi);

        foreach ([
            Response::HTTP_BAD_REQUEST => 'Bad Request',
            Response::HTTP_UNAUTHORIZED => 'Unauthorized',
            Response::HTTP_FORBIDDEN => 'Forbidden',
            Response::HTTP_NOT_FOUND => 'Not Found',
            Response::HTTP_TOO_MANY_REQUESTS => 'Too Many Requests',
        ] as $statusCode => $description) {
            static::assertArrayHasKey($statusCode, $responses, \sprintf('Default response for status %d is missing', $statusCode));
            static::assertSame($description, $responses[$statusCode]->description);
        }

        static::assertArrayNotHasKey(Response::HTTP_NO_CONTENT, $responses);
    }

    public function testEnrichAddsNoContentDefaultResponseForAdminApi(): void
    {
        $openApi = new OpenApi([]);

        (new OpenApiSchemaBuilder('6.7.0.0'))->enrich($openApi, DefinitionService::API);

        $responses = $this->getResponsesByStatusCode($openApi);

        static::assertArrayHasKey(Response::HTTP_NO_CONTENT, $responses);
        static::assertSame('No Content', $responses[Response::HTTP_NO_CONTENT]->description);
    }

    public function testEnrichUsesApiKeySecurityForStoreApi(): void
    {
        $openApi = new OpenApi([]);

        (new OpenApiSchemaBuilder('6.7.0.0'))->enrich($openApi, DefinitionService::STORE_API);

        static::assertSame([['ApiKey' => []]], $openApi->security);
        static::assertSame('Shopware Store API', $openApi->info->title);
    }

    public function testEnrichUsesOAuthSecurityForAdminApi(): void
    {
        $openApi = new OpenApi([]);

        (new OpenApiSchemaBuilder('6.7.0.0'))->enrich($openApi, DefinitionService::API);

        static::assertSame([['oAuth' => ['write']]], $openApi->security);
        static::assertSame('Shopware Admin API', $openApi->info->title);
    }

    #[DataProvider('serverUrlProvider')]
    public function testServerUrl(string $api, string $appUrl, string $appEnv, string $expectedUrl): void
    {
        $this->setEnvVars([
            'APP_ENV' => $appEnv,
            'APP_URL' => $appUrl,
        ]);
        $openApi = new OpenApi([]);

        (new OpenApiSchemaBuilder('6.7.0.0'))->enrich($openApi, $api);

        $schema = json_decode($openApi->toJson(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame($expectedUrl, $schema['servers'][0]['url']);
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

    public function testEnrichAddsRelationshipSchemasForAdminApi(): void
    {
        $openApi = new OpenApi([]);

        (new OpenApiSchemaBuilder('6.7.0.0'))->enrich($openApi, DefinitionService::API);

        $schema = json_decode($openApi->toJson(), true, flags: \JSON_THROW_ON_ERROR)['components']['schemas'];

        static::assertSame(
            ['$ref' => '#/components/schemas/relationship'],
            $schema['relationships']['additionalProperties']
        );
        static::assertEqualsCanonicalizing(['data', 'meta', 'links'], array_keys($schema['relationship']['properties']));
        static::assertSame(1, $schema['relationship']['minProperties']);
        static::assertFalse($schema['relationship']['additionalProperties']);
        static::assertArrayNotHasKey('anyOf', $schema['relationship']);
    }

    public function testEnrichDoesNotAddRelationshipSchemasForStoreApi(): void
    {
        $openApi = new OpenApi([]);

        (new OpenApiSchemaBuilder('6.7.0.0'))->enrich($openApi, DefinitionService::STORE_API);

        $schemas = json_decode($openApi->toJson(), true, flags: \JSON_THROW_ON_ERROR)['components']['schemas'] ?? [];

        foreach ([
            'success',
            'failure',
            'info',
            'meta',
            'data',
            'resource',
            'relationshipLinks',
            'links',
            'link',
            'attributes',
            'relationships',
            'relationship',
            'relationshipToOne',
            'relationshipToMany',
            'linkage',
            'pagination',
            'jsonapi',
            'error',
        ] as $schemaName) {
            static::assertArrayNotHasKey($schemaName, $schemas);
        }
    }

    /**
     * @return array<int, OpenApiResponse>
     */
    private function getResponsesByStatusCode(OpenApi $openApi): array
    {
        static::assertInstanceOf(Components::class, $openApi->components);
        static::assertIsArray($openApi->components->responses);

        $responses = [];
        foreach ($openApi->components->responses as $response) {
            static::assertIsInt($response->response);
            $responses[$response->response] = $response;
        }

        return $responses;
    }
}
