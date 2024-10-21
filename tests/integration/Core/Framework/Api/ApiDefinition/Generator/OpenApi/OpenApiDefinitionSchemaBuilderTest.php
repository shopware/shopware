<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\Api\ApiDefinition\EntityDefinition\SimpleDefinition;
use Shopware\Tests\Integration\Core\Framework\Api\ApiDefinition\EntityDefinition\SinceDefinition;

/**
 * @internal
 */
class OpenApiDefinitionSchemaBuilderTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour;
    use KernelTestBehaviour;

    /**
     * @var OpenApiDefinitionSchemaBuilder
     */
    private $service;

    protected function setUp(): void
    {
        $this->service = new OpenApiDefinitionSchemaBuilder();
    }

    public function testFieldIsMarkedAsNew(): void
    {
        $definition = $this->registerDefinition(SimpleDefinition::class);

        $build = json_decode(json_encode($this->service->getSchemaByDefinition($definition, '', false), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('Added since version: 6.0.0.0', $build['SimpleJsonApi']['description']);
        static::assertSame('Added since version: 6.3.9.9.', $build['SimpleJsonApi']['allOf'][1]['properties']['i_am_a_new_field']['description']);
    }

    public function testFieldIsMarkedAsNewWithJsonType(): void
    {
        $definition = $this->registerDefinition(SimpleDefinition::class);

        $build = json_decode(json_encode(
            $this->service->getSchemaByDefinition(
                $definition,
                '',
                false,
                false,
                DefinitionService::TYPE_JSON
            ),
            \JSON_THROW_ON_ERROR
        ), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('Added since version: 6.0.0.0', $build['Simple']['description']);
        static::assertSame('Added since version: 6.3.9.9.', $build['Simple']['properties']['i_am_a_new_field']['description']);
        static::assertArrayNotHasKey('SimpleJsonApi', $build);
    }

    public function testEntireDefinitionIsMarkedSince(): void
    {
        $definition = $this->registerDefinition(SinceDefinition::class);

        $build = json_decode(json_encode($this->service->getSchemaByDefinition($definition, '', false), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('Added since version: 6.3.9.9', $build['SinceJsonApi']['description']);
        static::assertArrayNotHasKey('description', $build['SinceJsonApi']['allOf'][1]['properties']['id']);
    }

    public function testEntireDefinitionIsMarkedSinceWithJsonType(): void
    {
        $definition = $this->registerDefinition(SinceDefinition::class);

        $build = json_decode(json_encode(
            $this->service->getSchemaByDefinition(
                $definition,
                '',
                false,
                false,
                DefinitionService::TYPE_JSON
            ),
            \JSON_THROW_ON_ERROR
        ), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('Added since version: 6.3.9.9', $build['Since']['description']);
        static::assertArrayNotHasKey('description', $build['Since']['properties']['id']);
        static::assertArrayNotHasKey('SinceJsonApi', $build);
    }
}
