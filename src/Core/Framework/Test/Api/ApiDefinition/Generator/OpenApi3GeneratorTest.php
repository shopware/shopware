<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\Generator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\ORM\DefinitionRegistry;
use Shopware\Core\Framework\Test\Api\ApiDefinition\EntityDefinition\SimpleDefinition;

class OpenApi3GeneratorTest extends TestCase
{
    /**
     * @var array
     */
    private $schema;

    /**
     * @var string string
     */
    private $entityName;

    public function __construct()
    {
        parent::__construct();

        $definitionRegistry = new DefinitionRegistry([SimpleDefinition::class]);
        $openApiGenerator = new OpenApi3Generator($definitionRegistry);
        $this->schema = $openApiGenerator->getSchema();
        $this->entityName = SimpleDefinition::getEntityName();
    }

    public function testEntityNameConversion()
    {
        static::assertArrayHasKey(SimpleDefinition::getEntityName(), $this->schema);
        static::assertEquals($this->entityName, $this->schema[$this->entityName]['name']);
    }

    public function testTypeConversion()
    {
        $properties = $this->schema[$this->entityName]['properties'];

        static::assertArraySubset(['type' => 'string', 'format' => 'uuid'], $properties['id']);
        static::assertArraySubset(['type' => 'string'], $properties['stringField']);
        static::assertArraySubset(['type' => 'integer', 'format' => 'int64'], $properties['intField']);
        static::assertArraySubset(['type' => 'number', 'format' => 'float'], $properties['floatField']);
        static::assertArraySubset(['type' => 'boolean'], $properties['boolField']);
        static::assertArraySubset(['type' => 'string'], $properties['stringField']);
        static::assertArraySubset(['type' => 'integer', 'format' => 'int64'], $properties['childCount']);
    }

    public function testFlagConversion()
    {
        $properties = $this->schema[$this->entityName]['properties'];
        $requiredFields = $this->schema[$this->entityName]['required'];

        static::assertArraySubset(['requiredField'], $requiredFields);
        static::assertArraySubset(['readOnly' => true], $properties['readOnlyField']);
    }
}
