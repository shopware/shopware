<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiPathBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SimpleDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures\ImmutableFieldsDefinition;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(OpenApiPathBuilder::class)]
class OpenApiPathBuilderTest extends TestCase
{
    private OpenApiPathBuilder $pathBuilder;

    private StaticDefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->pathBuilder = new OpenApiPathBuilder();
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [
                SimpleDefinition::class,
                ImmutableFieldsDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    public function testGetPathActionsReturnsAllPaths(): void
    {
        $definition = $this->definitionRegistry->get(SimpleDefinition::class);
        $paths = $this->pathBuilder->getPathActions($definition, '/simple');

        static::assertArrayHasKey('/simple', $paths);
        static::assertArrayHasKey('/search/simple', $paths);
        static::assertArrayHasKey('/simple/{id}', $paths);
        static::assertArrayHasKey('/aggregate/simple', $paths);
    }

    public function testGetPathActionsWithImmutableFieldsUsesCreateSchema(): void
    {
        $definition = $this->definitionRegistry->get(ImmutableFieldsDefinition::class);
        $paths = $this->pathBuilder->getPathActions($definition, '/immutable-test');

        // POST should use Create schema when entity has immutable fields
        $postPath = $paths['/immutable-test']->post;
        static::assertNotNull($postPath);

        $postPathJson = json_decode($postPath->toJson(), true, 512, \JSON_THROW_ON_ERROR);
        $requestBodySchema = $postPathJson['requestBody']['content']['application/json']['schema']['$ref'];

        static::assertSame('#/components/schemas/ImmutableTestCreate', $requestBodySchema);
    }

    public function testGetPathActionsWithoutImmutableFieldsUsesUpdateSchema(): void
    {
        $definition = $this->definitionRegistry->get(SimpleDefinition::class);
        $paths = $this->pathBuilder->getPathActions($definition, '/simple');

        // POST should use Update schema when entity has no immutable fields
        $postPath = $paths['/simple']->post;
        static::assertNotNull($postPath);

        $postPathJson = json_decode($postPath->toJson(), true, 512, \JSON_THROW_ON_ERROR);
        $requestBodySchema = $postPathJson['requestBody']['content']['application/json']['schema']['$ref'];

        static::assertSame('#/components/schemas/SimpleUpdate', $requestBodySchema);
    }

    public function testGetPathActionsPatchUsesUpdateSchema(): void
    {
        $definition = $this->definitionRegistry->get(ImmutableFieldsDefinition::class);
        $paths = $this->pathBuilder->getPathActions($definition, '/immutable-test');

        // PATCH should always use Update schema
        $patchPath = $paths['/immutable-test/{id}']->patch;
        static::assertNotNull($patchPath);

        $patchPathJson = json_decode($patchPath->toJson(), true, 512, \JSON_THROW_ON_ERROR);
        $requestBodySchema = $patchPathJson['requestBody']['content']['application/json']['schema']['$ref'];

        static::assertSame('#/components/schemas/ImmutableTestUpdate', $requestBodySchema);
    }

    public function testGetTag(): void
    {
        $definition = $this->definitionRegistry->get(SimpleDefinition::class);
        $tag = $this->pathBuilder->getTag($definition);

        static::assertSame('Simple', $tag->name);
    }
}
