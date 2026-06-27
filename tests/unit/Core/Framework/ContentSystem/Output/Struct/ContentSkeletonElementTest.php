<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentSkeletonElement;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;

/**
 * @internal
 */
#[CoversClass(ContentSkeletonElement::class)]
class ContentSkeletonElementTest extends TestCase
{
    #[TestDox('converts elements recursively into skeleton structure preserving IDs and components')]
    public function testFromElements(): void
    {
        $root = ContentElementBuilder::create('section', 'root-1')
            ->withProperty('background', 'blue')
            ->build();

        $skeletons = ContentSkeletonElement::fromElements([$root]);

        static::assertCount(1, $skeletons);
        static::assertSame('root-1', $skeletons[0]->id);
        static::assertSame('section', $skeletons[0]->component);
        static::assertSame([], $skeletons[0]->slots);
    }

    #[TestDox('preserves slot structure in skeleton including nested children')]
    public function testFromElementsPreservesSlots(): void
    {
        $grandchild = ContentElementBuilder::create('image', 'gc-1')->build();

        $child = ContentElementBuilder::create('text', 'child-1')
            ->withSlot('media', [$grandchild])
            ->build();

        $root = ContentElementBuilder::create('section', 'root-1')
            ->withSlot('content', [$child])
            ->build();

        $skeletons = ContentSkeletonElement::fromElements([$root]);

        static::assertArrayHasKey('content', $skeletons[0]->slots);
        static::assertCount(1, $skeletons[0]->slots['content']);

        $childSkeleton = $skeletons[0]->slots['content'][0];
        static::assertSame('child-1', $childSkeleton->id);
        static::assertArrayHasKey('media', $childSkeleton->slots);
        static::assertCount(1, $childSkeleton->slots['media']);
        static::assertSame('gc-1', $childSkeleton->slots['media'][0]->id);
    }

    #[TestDox('carries the element style into the skeleton including nested children')]
    public function testFromElementsCarriesStyle(): void
    {
        $childStyle = new ElementStyle(['display' => ['xs' => false]]);
        $child = ContentElementBuilder::create('text', 'child-1')->withStyle($childStyle)->build();

        $rootStyle = new ElementStyle(['col-span' => ['md' => 6]]);
        $root = ContentElementBuilder::create('section', 'root-1')
            ->withStyle($rootStyle)
            ->withSlot('content', [$child])
            ->build();

        $skeletons = ContentSkeletonElement::fromElements([$root]);

        static::assertSame($rootStyle->toArray(), $skeletons[0]->style->toArray());
        static::assertSame($childStyle->toArray(), $skeletons[0]->slots['content'][0]->style->toArray());
    }

    #[TestDox('returns empty array when given an empty iterable')]
    public function testFromElementsWithEmptyInput(): void
    {
        $skeletons = ContentSkeletonElement::fromElements([]);

        static::assertSame([], $skeletons);
    }

    #[TestDox('serializes style as the wire array when present')]
    public function testSerializesStyleAsArrayWhenPresent(): void
    {
        $root = ContentElementBuilder::create('section', 'root-1')
            ->withStyle(new ElementStyle(['col-span' => ['md' => 6]]))
            ->build();

        $data = ContentSkeletonElement::fromElements([$root])[0]->jsonSerialize();

        static::assertSame(['col-span' => ['md' => 6]], $data['style']);
    }

    #[TestDox('omits the style key from serialization when the element has no style')]
    public function testSerializesWithoutStyleWhenEmpty(): void
    {
        $root = ContentElementBuilder::create('section', 'root-1')->build();

        $data = ContentSkeletonElement::fromElements([$root])[0]->jsonSerialize();

        static::assertArrayNotHasKey('style', $data);
    }
}
