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
    #[TestDox('preserves property keys when converting to specification')]
    public function testPreservesPropertyKeysOnConversion(): void
    {
        $dto = $this->createDto(
            [
                'title' => new PropertySpecificationDto('title', 'string', false, true, 'Title', '', null, null, null),
                'layout' => new PropertySpecificationDto('layout', 'string', false, false, 'Layout', '', ['box', 'list'], 'box', null),
            ],
            [],
        );

        $schema = $dto->toContentElementTypeSpecification()->toSchema();

        static::assertCount(2, $schema['properties']);
        static::assertArrayHasKey('title', $schema['properties']);
        static::assertArrayHasKey('layout', $schema['properties']);
    }

    #[TestDox('converts slot DTOs into specification slots')]
    public function testConvertsSlotsToSpecification(): void
    {
        $dto = $this->createDto(
            [],
            [new SlotSpecificationDto('media', 1, ['Sw:Media:Image'], 'Media slot.')],
        );

        $schema = $dto->toContentElementTypeSpecification()->toSchema();

        static::assertCount(1, $schema['slots']);
    }

    #[TestDox('produces correct name and empty collections when no properties or slots are defined')]
    public function testConvertsEmptyDtoToSpecification(): void
    {
        $dto = $this->createDto([], []);
        $spec = $dto->toContentElementTypeSpecification();

        static::assertSame('Sw:Product:Card', $spec->name());

        $schema = $spec->toSchema();

        static::assertSame([], $schema['properties']);
        static::assertSame([], $schema['slots']);
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
