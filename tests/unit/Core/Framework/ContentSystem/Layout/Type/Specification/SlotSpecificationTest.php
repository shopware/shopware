<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;

/**
 * @internal
 */
#[CoversClass(SlotSpecification::class)]
class SlotSpecificationTest extends TestCase
{
    /**
     * @param list<string> $allowList
     * @param array{name: string, maxElements: int|null, allowList: list<string>, description: string} $expectedSchema
     */
    #[DataProvider('serializesAllFieldsProvider')]
    #[TestDox('serializes all constructor fields into schema array for name=$name')]
    public function testToSchemaSerializesAllFields(
        string $name,
        ?int $maxElements,
        array $allowList,
        string $description,
        array $expectedSchema,
    ): void {
        $slot = new SlotSpecification($name, $maxElements, $allowList, $description);

        static::assertSame($expectedSchema, $slot->toSchema());
    }

    /**
     * @return iterable<string, array{string, int|null, list<string>, string, array{name: string, maxElements: int|null, allowList: list<string>, description: string}}>
     */
    public static function serializesAllFieldsProvider(): iterable
    {
        yield 'all fields populated' => [
            'media',
            3,
            ['Sw:Media:Image', 'Sw:Media:Video'],
            'Media slot for product image.',
            [
                'name' => 'media',
                'maxElements' => 3,
                'allowList' => ['Sw:Media:Image', 'Sw:Media:Video'],
                'description' => 'Media slot for product image.',
            ],
        ];

        yield 'null maxElements and empty allowList' => [
            'content',
            null,
            [],
            '',
            [
                'name' => 'content',
                'maxElements' => null,
                'allowList' => [],
                'description' => '',
            ],
        ];
    }
}
