<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\CopilotSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\ElementTypeSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\SlotSpecificationDto;

/**
 * @internal
 */
#[CoversClass(ElementTypeSpecificationDto::class)]
class ElementTypeSpecificationDtoTest extends TestCase
{
    #[TestDox('converts DTO to specification preserving name, source, property keys, and slot count')]
    public function testConvertsToSpecificationPreservingStructure(): void
    {
        $dto = new ElementTypeSpecificationDto(
            'Product Card',
            'A product card.',
            'shopware AG',
            'card',
            'commerce',
            new CopilotSpecificationDto('Summary.', ['Hint.']),
            [
                'custom_key' => new PropertySpecificationDto('different_name', 'string', true, false, 'Title', '', null, null, null),
                'layout' => new PropertySpecificationDto('layout', 'string', false, false, 'Layout', '', ['box', 'list'], 'box', null),
            ],
            [new SlotSpecificationDto('media', 1, ['Sw:Media:Image'], 'Media slot.')],
        );

        $specification = $dto->toContentSystemElementTypeSpecification('Sw:Product:Card', 'plugin:MyPlugin');

        static::assertSame('Sw:Product:Card', $specification->name());
        static::assertSame('plugin:MyPlugin', $specification->source());

        $schema = $specification->toSchema();

        // Property keys come from the outer array key, not PropertySpecificationDto::name
        static::assertArrayHasKey('custom_key', $schema['properties']);
        static::assertArrayNotHasKey('different_name', $schema['properties']);
        static::assertCount(2, $schema['properties']);
        static::assertCount(1, $schema['slots']);
    }
}
