<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiPathBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\CustomBundleWithApiSchema\ShopwareBundleWithName;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SimpleDefinition;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(OpenApi3Generator::class)]
class OpenApi3GeneratorTest extends TestCase
{
    private OpenApi3Generator $generator;

    private OpenApi3Generator $customApiGenerator;

    private ShopwareBundleWithName $customBundleSchemas;

    private StaticDefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->generator = new OpenApi3Generator(
            new OpenApiSchemaBuilder('0.1.0'),
            new OpenApiPathBuilder(),
            new OpenApiDefinitionSchemaBuilder(),
            [
                'Framework' => ['path' => __DIR__ . '/_fixtures'],
            ],
            new BundleSchemaPathCollection([])
        );

        $this->customBundleSchemas = new ShopwareBundleWithName();
        $customBundlePathCollection = new BundleSchemaPathCollection([$this->customBundleSchemas]);

        $this->customApiGenerator = new OpenApi3Generator(
            new OpenApiSchemaBuilder('0.1.0'),
            new OpenApiPathBuilder(),
            new OpenApiDefinitionSchemaBuilder(),
            [
                'Framework' => ['path' => __DIR__ . '/_fixtures'],
            ],
            $customBundlePathCollection
        );

        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [
                SimpleDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    public function testSchemaContainsCorrectPaths(): void
    {
        $schema = $this->generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::API
        );
        $paths = $schema['paths'];

        static::assertArrayHasKey('get', $paths['/simple']);
        static::assertArrayHasKey('post', $paths['/simple']);
        static::assertArrayHasKey('get', $paths['/simple/{id}']);
        static::assertArrayHasKey('patch', $paths['/simple/{id}']);
        static::assertArrayHasKey('delete', $paths['/simple/{id}']);

        static::assertArrayHasKey('post', $paths['/_action/order_delivery/{orderDeliveryId}/state/{transition}']);
    }

    public function testSchemaContainsCorrectEntities(): void
    {
        $schema = $this->generator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::API,
            'json',
            null
        );
        $entities = $schema['components']['schemas'];
        static::assertArrayHasKey('Simple', $entities);
        static::assertArrayHasKey('infoConfigResponse', $entities);
    }

    public function testSchemaBuilding(): void
    {
        $schema = $this->generator->getSchema(
            $this->definitionRegistry->getDefinitions()
        );

        static::assertArrayHasKey('simple', $schema);
    }

    public function testSchemaContainsCustomPathsOnly(): void
    {
        $schema = $this->customApiGenerator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::API,
            DefinitionService::TYPE_JSON_API,
            $this->customBundleSchemas->getName()
        );

        $paths = $schema['paths'];

        static::assertArrayHasKey('post', $paths['/search/guided-shopping-presentation']);
        static::assertArrayNotHasKey('/_action/order_delivery/{orderDeliveryId}/state/{transition}', $paths);
    }

    public function testSchemaContainsCustomEntitiesOnly(): void
    {
        $schema = $this->customApiGenerator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::API,
            DefinitionService::TYPE_JSON_API,
            $this->customBundleSchemas->getName()
        );

        $entities = $schema['components']['schemas'];
        static::assertArrayHasKey('Presentation', $entities);
        static::assertArrayHasKey('infoConfigResponse', $entities);
        static::assertEquals('Experimental', $schema['tags'][0]['name'] ?? null);
    }
}
