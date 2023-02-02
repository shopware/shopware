<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiPathBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SimpleDefinition;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @covers \Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator
 *
 * @internal
 */
class OpenApi3GeneratorTest extends TestCase
{
    private OpenApi3Generator $generator;

    private StaticDefinitionInstanceRegistry $definitionRegistry;

    public function setUp(): void
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
            DefinitionService::API,
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
}
