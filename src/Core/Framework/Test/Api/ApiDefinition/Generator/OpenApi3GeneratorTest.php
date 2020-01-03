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
            new OpenApiLoader(__DIR__)
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
        $this->silentAssertArraySubset(['type' => 'string', 'format' => 'uuid'], $properties['id']);
        $this->silentAssertArraySubset(['type' => 'string'], $properties['stringField']);
        $this->silentAssertArraySubset(['type' => 'integer', 'format' => 'int64'], $properties['intField']);
        $this->silentAssertArraySubset(['type' => 'number', 'format' => 'float'], $properties['floatField']);
        $this->silentAssertArraySubset(['type' => 'boolean'], $properties['boolField']);
        $this->silentAssertArraySubset(['type' => 'string'], $properties['stringField']);
        $this->silentAssertArraySubset(['type' => 'integer', 'format' => 'int64'], $properties['childCount']);
    }

    public function testFlagConversion(): void
    {
        $properties = $this->schema[$this->entityName]['properties'];
        $requiredFields = $this->schema[$this->entityName]['required'];

        $this->silentAssertArraySubset(['requiredField'], $requiredFields);
        $this->silentAssertArraySubset(['readOnly' => true], $properties['readOnlyField']);
    }
}
