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
    #[TestDox('maps property keys to schema')]
    public function testMapsPropertyKeysToSchema(): void
    {
        $dto = $this->createDto(
            [
                'title' => new PropertySpecificationDto('title', 'string', false, true, 'Title', '', null, null, null),
                'layout' => new PropertySpecificationDto('layout', 'string', false, false, 'Layout', '', ['box', 'list'], 'box', null),
            ],
            [],
        );

        $schema = $dto->toContentSystemElementTypeSpecification('test')->toSchema();

        static::assertCount(2, $schema['properties']);
        static::assertArrayHasKey('title', $schema['properties']);
        static::assertArrayHasKey('layout', $schema['properties']);
    }

    #[TestDox('includes slots in schema')]
    public function testIncludesSlotsInSchema(): void
    {
        $dto = $this->createDto(
            [],
            [new SlotSpecificationDto('media', 1, ['Sw:Media:Image'], 'Media slot.')],
        );

        $schema = $dto->toContentSystemElementTypeSpecification('test')->toSchema();

        static::assertCount(1, $schema['slots']);
    }

    #[TestDox('returns empty properties and slots when none are defined')]
    public function testReturnsEmptyCollectionsWhenNoneAreDefined(): void
    {
        $dto = $this->createDto([], []);
        $schema = $dto->toContentSystemElementTypeSpecification('test')->toSchema();

        static::assertSame([], $schema['properties']);
        static::assertSame([], $schema['slots']);
    }

    #[TestDox('passes source label through to specification')]
    public function testSourceIsPassedThroughToSpecification(): void
    {
        $dto = new ElementTypeSpecificationDto(
            'Sw:Content:Text',
            'Text',
            '',
            'vendor',
            null,
            null,
            new CopilotSpecificationDto('', []),
            [],
            [],
        );

        $specification = $dto->toContentSystemElementTypeSpecification('plugin:MyPlugin');

        static::assertSame('plugin:MyPlugin', $specification->source());
    }

    /**
     * @param array<string, PropertySpecificationDto> $properties
     * @param list<SlotSpecificationDto> $slots
     */
    private function createDto(array $properties, array $slots): ElementTypeSpecificationDto
    {
        return new ElementTypeSpecificationDto(
            'Sw:Product:Card',
            'Card',
            'Card.',
            'shopware AG',
            null,
            null,
            new CopilotSpecificationDto('', []),
            $properties,
            $slots,
        );
    }
}
