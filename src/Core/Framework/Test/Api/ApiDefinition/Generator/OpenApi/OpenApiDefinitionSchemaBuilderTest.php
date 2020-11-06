<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\Generator\OpenApi;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Test\Api\ApiDefinition\EntityDefinition\SimpleDefinition;
use Shopware\Core\Framework\Test\Api\ApiDefinition\EntityDefinition\SinceDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class OpenApiDefinitionSchemaBuilderTest extends TestCase
{
    use KernelTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var OpenApiDefinitionSchemaBuilder
     */
    private $service;

    protected function setUp(): void
    {
        $this->service = $this->getContainer()->get(OpenApiDefinitionSchemaBuilder::class);
    }

    public function testFieldIsMarkedAsNew(): void
    {
        $definition = $this->registerDefinition(SimpleDefinition::class);

        $build = json_decode(json_encode($this->service->getSchemaByDefinition($definition, '', false, 1)), true);

        static::assertSame('Added since version: 6.0.0.0', $build['simple']['description']);
        static::assertSame('Added since version: 6.3.9.9', $build['simple']['allOf'][1]['properties']['i_am_a_new_field']['description']);
    }

    public function testEntireDefinitionIsMarkedSince(): void
    {
        $definition = $this->registerDefinition(SinceDefinition::class);

        $build = json_decode(json_encode($this->service->getSchemaByDefinition($definition, '', false, 1)), true);

        static::assertSame('Added since version: 6.3.9.9', $build['since']['description']);
        static::assertArrayNotHasKey('description', $build['since']['allOf'][1]['properties']['id']);
    }
}
