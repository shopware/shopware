<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\Components;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Response as OpenApiResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(OpenApiSchemaBuilder::class)]
class OpenApiSchemaBuilderTest extends TestCase
{
    public function testEnrichAddsDefaultErrorResponses(): void
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
            Response::HTTP_NO_CONTENT => 'No Content',
        ] as $statusCode => $description) {
            static::assertArrayHasKey($statusCode, $responses, \sprintf('Default response for status %d is missing', $statusCode));
            static::assertSame($description, $responses[$statusCode]->description);
        }
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
