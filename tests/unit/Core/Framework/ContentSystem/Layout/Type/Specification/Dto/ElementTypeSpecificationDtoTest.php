<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\CopilotSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\ElementTypeSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;

/**
 * @internal
 */
#[CoversClass(ElementTypeSpecificationDto::class)]
class ElementTypeSpecificationDtoTest extends TestCase
{
    #[TestDox('preserves property keys through mapping loop')]
    public function testPreservesPropertyKeysInMapping(): void
    {
        $dto = new ElementTypeSpecificationDto(
            'Sw:Product:Card',
            'Card',
            'Card.',
            'shopware AG',
            null,
            null,
            new CopilotSpecificationDto('', []),
            [
                'title' => new PropertySpecificationDto('title', 'string', false, true, 'Title', '', null, null, null),
                'layout' => new PropertySpecificationDto('layout', 'string', false, false, 'Layout', '', ['box', 'list'], 'box', null),
            ],
            [],
        );

        $schema = $dto->toContentElementTypeSpecification()->toSchema();

        static::assertArrayHasKey('title', $schema['properties']);
        static::assertArrayHasKey('layout', $schema['properties']);
        static::assertSame('string', $schema['properties']['title']['type']);
        static::assertSame(['box', 'list'], $schema['properties']['layout']['enum']);
    }
}
