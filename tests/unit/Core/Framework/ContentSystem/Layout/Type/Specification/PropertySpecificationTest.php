<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;

/**
 * @internal
 */
#[CoversClass(PropertySpecification::class)]
class PropertySpecificationTest extends TestCase
{
    /**
     * @param array<string, mixed>|null $adminUI
     * @param array{type: string, translatable: bool, enum: list<string|int|float|bool>|null, default: string|int|float|bool|null, required: bool, title: string, description: string, adminUI: array<string, mixed>|null} $expectedSchema
     */
    #[DataProvider('returnsSchemaProvider')]
    #[TestDox('returns schema with correct field values')]
    public function testReturnsSchemaWithCorrectFields(
        PropertyType $type,
        string $name,
        bool $required,
        string $title,
        string $description,
        ?array $adminUI,
        array $expectedSchema,
    ): void {
        $prop = new PropertySpecification($name, $type, $required, $title, $description, $adminUI);

        static::assertSame($expectedSchema, $prop->toSchema());
    }

    /**
     * @return iterable<string, array{PropertyType, string, bool, string, string, array<string, mixed>|null, array<string, mixed>}>
     */
    public static function returnsSchemaProvider(): iterable
    {
        yield 'all fields populated including adminUI' => [
            new PropertyType('string', false, ['box', 'list'], 'box'),
            'layout',
            true,
            'Card Layout',
            'Layout variant.',
            ['field' => 'mt:text:field'],
            [
                'type' => 'string',
                'translatable' => false,
                'enum' => ['box', 'list'],
                'default' => 'box',
                'required' => true,
                'title' => 'Card Layout',
                'description' => 'Layout variant.',
                'adminUI' => ['field' => 'mt:text:field'],
            ],
        ];
    }
}
