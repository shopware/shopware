<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\Generator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiLoader;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiPathBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\Api\ApiDefinition\EntityDefinition\SimpleDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class OpenApi3GeneratorTest extends TestCase
{
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

    /**
     * @var OpenApi3Generator
     */
    private $openApiGenerator;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    protected function setUp(): void
    {
        $this->registerDefinition(SimpleDefinition::class);

        $this->definitionRegistry = new DefinitionInstanceRegistry(
            $this->getContainer(),
            ['simple' => SimpleDefinition::class],
            ['simple' => 'simple.repository']
        );
        $this->openApiGenerator = new OpenApi3Generator(
            new OpenApiSchemaBuilder('6.4.0.0'),
            new OpenApiPathBuilder(),
            new OpenApiDefinitionSchemaBuilder(),
            new OpenApiLoader($this->getContainer()->get('router'), $this->getContainer()->get('event_dispatcher')),
            $this->getContainer()->getParameter('kernel.bundles_metadata')
        );

        $this->schema = $this->openApiGenerator->getSchema($this->definitionRegistry->getDefinitions());
        $this->entityName = 'simple';
    }

    public function testGenerateStoreApiSchemaFeaturedInternalActive(): void
    {
        Feature::registerFeature('FEATURE_NEXT_12345', ['default' => true]);
        $generatedSchema = $this->openApiGenerator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API
        );

        static::assertArrayHasKey('paths', $generatedSchema);

        //check for class internal annotation
        static::assertArrayHasKey('/testinternal', $generatedSchema['paths']);

        //check for method internal with flag
        static::assertArrayHasKey('/testinternalother', $generatedSchema['paths']);

        //check for method not internal
        static::assertArrayHasKey('/testnotinternalother', $generatedSchema['paths']);

        //check for method internal without flag
        static::assertArrayNotHasKey('/testinternalnoflagother', $generatedSchema['paths']);
    }

    public function testGenerateStoreApiSchemaFeaturedInternalInActive(): void
    {
        if (static::isFeatureAllTrue()) {
            static::markTestSkipped('skipped because FEATURE_ALL is set');
        }
        Feature::registerFeature('FEATURE_NEXT_12345', ['default' => false]);
        $generatedSchema = $this->openApiGenerator->generate(
            $this->definitionRegistry->getDefinitions(),
            DefinitionService::STORE_API
        );
        static::assertArrayHasKey('paths', $generatedSchema);

        //check for class internal annotation
        static::assertArrayNotHasKey('/testinternal', $generatedSchema['paths']);

        //check for method internal with flag
        static::assertArrayNotHasKey('/testinternalother', $generatedSchema['paths']);

        //check for method not internal
        static::assertArrayHasKey('/testnotinternalother', $generatedSchema['paths']);

        //check for method internal without flag
        static::assertArrayNotHasKey('/testinternalnoflagother', $generatedSchema['paths']);
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
        static::assertArrayHasKey('pattern', $properties['id']);
        static::assertEquals('^[0-9a-f]{32}$', $properties['id']['pattern']);
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

    private static function isFeatureAllTrue(): bool
    {
        $value = $_SERVER['FEATURE_ALL'] ?? 'false';

        return $value
            && $value !== 'false'
            && $value !== '0'
            && $value !== '';
    }
}
