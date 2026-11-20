<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentSkeletonElement;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSkeletonElement::class)]
class ContentSkeletonElementTest extends TestCase
{
    #[TestDox('projects a rendered element onto id, component and slots, dropping its property values')]
    public function testFromRenderedStripsProperties(): void
    {
        $element = new RenderedElement('root-1', 'section', ['background' => 'blue']);

        $skeletons = ContentSkeletonElement::fromRendered([$element]);

        static::assertCount(1, $skeletons);
        static::assertSame('root-1', $skeletons[0]->id);
        static::assertSame('section', $skeletons[0]->component);
        static::assertSame([], $skeletons[0]->slots);
        static::assertSame(['id', 'component', 'slots'], array_keys($skeletons[0]->jsonSerialize()));
    }

    #[TestDox('projects a rendered forest recursively, keeping slot names and child order')]
    public function testFromRenderedPreservesSlotStructure(): void
    {
        $grandchild = new RenderedElement('gc-1', 'image');
        $child = new RenderedElement('child-1', 'text', [], ['media' => [$grandchild]]);
        $second = new RenderedElement('child-2', 'text');
        $root = new RenderedElement('root-1', 'section', [], ['content' => [$child, $second]]);

        $skeletons = ContentSkeletonElement::fromRendered([$root]);

        static::assertSame(['content'], array_keys($skeletons[0]->slots));
        static::assertSame(['child-1', 'child-2'], array_column($skeletons[0]->slots['content'], 'id'));

        $childSkeleton = $skeletons[0]->slots['content'][0];
        static::assertSame(['media'], array_keys($childSkeleton->slots));
        static::assertSame('gc-1', $childSkeleton->slots['media'][0]->id);
    }

    #[TestDox('carries the rendered element style into the skeleton at every depth')]
    public function testFromRenderedCarriesStyle(): void
    {
        $childStyle = new ElementStyle(['display' => ['xs' => false]]);
        $rootStyle = new ElementStyle(['col-span' => ['md' => 6]]);

        $child = new RenderedElement('child-1', 'text', [], [], $childStyle);
        $root = new RenderedElement('root-1', 'section', [], ['content' => [$child]], $rootStyle);

        $skeletons = ContentSkeletonElement::fromRendered([$root]);

        static::assertSame($rootStyle->toArray(), $skeletons[0]->style->toArray());
        static::assertSame($childStyle->toArray(), $skeletons[0]->slots['content'][0]->style->toArray());
    }

    #[TestDox('returns an empty list for an empty rendered forest')]
    public function testFromRenderedWithEmptyInput(): void
    {
        static::assertSame([], ContentSkeletonElement::fromRendered([]));
    }

    #[TestDox('serializes style as the wire array when present')]
    public function testSerializesStyleAsArrayWhenPresent(): void
    {
        $root = new RenderedElement('root-1', 'section', [], [], new ElementStyle(['col-span' => ['md' => 6]]));

        $data = ContentSkeletonElement::fromRendered([$root])[0]->jsonSerialize();

        static::assertSame(['col-span' => ['md' => 6]], $data['style']);
    }

    #[TestDox('omits the style key from serialization when the element has no style')]
    public function testSerializesWithoutStyleWhenEmpty(): void
    {
        $root = new RenderedElement('root-1', 'section');

        $data = ContentSkeletonElement::fromRendered([$root])[0]->jsonSerialize();

        static::assertArrayNotHasKey('style', $data);
    }

    #[TestDox('serializes exactly id, component, slots and style at every depth for a styled element')]
    public function testSerializedKeySetWithStyleAtEveryDepth(): void
    {
        $child = new RenderedElement('child-1', 'text', [], [], new ElementStyle(['display' => ['xs' => false]]));
        $root = new RenderedElement('root-1', 'section', [], ['content' => [$child]], new ElementStyle(['col-span' => ['md' => 6]]));

        $encoded = $this->encode($root);

        static::assertSame(['id', 'component', 'slots', 'style'], array_keys($encoded));

        $childEncoded = $encoded['slots']['content'][0];
        static::assertIsArray($childEncoded);
        static::assertSame(['id', 'component', 'slots', 'style'], array_keys($childEncoded));
    }

    #[TestDox('serializes exactly id, component and slots at every depth for an unstyled element')]
    public function testSerializedKeySetWithoutStyleAtEveryDepth(): void
    {
        $child = new RenderedElement('child-1', 'text');
        $root = new RenderedElement('root-1', 'section', [], ['content' => [$child]]);

        $encoded = $this->encode($root);

        static::assertSame(['id', 'component', 'slots'], array_keys($encoded));

        $childEncoded = $encoded['slots']['content'][0];
        static::assertIsArray($childEncoded);
        static::assertSame(['id', 'component', 'slots'], array_keys($childEncoded));
    }

    /**
     * Mirrors the json round-trip the response normalizer performs, so nested nodes are pinned in their wire shape.
     *
     * @return array<string, mixed>
     */
    private function encode(RenderedElement $root): array
    {
        $skeleton = ContentSkeletonElement::fromRendered([$root])[0];

        $encoded = json_decode(json_encode($skeleton, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($encoded);

        return $encoded;
    }
}
