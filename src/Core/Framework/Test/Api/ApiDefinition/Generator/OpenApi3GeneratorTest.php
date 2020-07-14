<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\Generator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiLoader;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiPathBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\Api\ApiDefinition\EntityDefinition\SimpleDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AssertArraySubsetBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\PlatformRequest;

class OpenApi3GeneratorTest extends TestCase
{
    use AssertArraySubsetBehaviour;
    use IntegrationTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var array
     */
    private $schema;

    /**
     * @var string string
     */
    private $entityName;

    protected function setUp(): void
    {
        $this->registerDefinition(SimpleDefinition::class);

        $definitionRegistry = new DefinitionInstanceRegistry(
            $this->getContainer(),
            ['simple' => SimpleDefinition::class],
            ['simple' => 'simple.repository']
        );
        $openApiGenerator = new OpenApi3Generator(
            new OpenApiSchemaBuilder(),
            new OpenApiPathBuilder(),
            new OpenApiDefinitionSchemaBuilder($this->getContainer()->get(ApiVersionConverter::class)),
            new OpenApiLoader($this->getContainer()->get('router'))
        );

        $this->schema = $openApiGenerator->getSchema($definitionRegistry->getDefinitions(), PlatformRequest::API_VERSION);
        $this->entityName = 'simple';
    }

    public function testEntityNameConversion(): void
    {
        static::assertArrayHasKey($this->entityName, $this->schema);
        static::assertEquals($this->entityName, $this->schema[$this->entityName]['name']);
    }

    public function testTypeConversion(): void
    {
        $properties = $this->schema[$this->entityName]['properties'];
        static::assertArrayHasKey('id', $properties);
        static::assertArrayHasKey('type', $properties['id']);
        static::assertEquals('string', $properties['id']['type']);
        static::assertArrayHasKey('format', $properties['id']);
        static::assertEquals('uuid', $properties['id']['format']);
        static::assertArrayHasKey('stringField', $properties);
        static::assertArrayHasKey('type', $properties['stringField']);
        static::assertEquals('string', $properties['stringField']['type']);
        static::assertArrayHasKey('intField', $properties);
        static::assertArrayHasKey('type', $properties['intField']);
        static::assertEquals('integer', $properties['intField']['type']);
        static::assertArrayHasKey('format', $properties['intField']);
        static::assertEquals('int64', $properties['intField']['format']);
        static::assertArrayHasKey('floatField', $properties);
        static::assertArrayHasKey('type', $properties['floatField']);
        static::assertEquals('number', $properties['floatField']['type']);
        static::assertArrayHasKey('format', $properties['floatField']);
        static::assertEquals('float', $properties['floatField']['format']);
        static::assertArrayHasKey('boolField', $properties);
        static::assertArrayHasKey('type', $properties['boolField']);
        static::assertEquals('boolean', $properties['boolField']['type']);
        static::assertArrayHasKey('childCount', $properties);
        static::assertArrayHasKey('type', $properties['childCount']);
        static::assertEquals('integer', $properties['childCount']['type']);
        static::assertArrayHasKey('format', $properties['childCount']);
        static::assertEquals('int64', $properties['childCount']['format']);
    }

    public function testFlagConversion(): void
    {
        $properties = $this->schema[$this->entityName]['properties'];

        static::assertArrayHasKey('requiredField', $properties);
        static::assertArrayHasKey('readOnlyField', $properties);
        static::assertArrayHasKey('readOnly', $properties['readOnlyField']);
        static::assertTrue($properties['readOnlyField']['readOnly']);
    }
}
