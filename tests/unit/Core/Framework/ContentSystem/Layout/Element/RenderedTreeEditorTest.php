<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\RenderedElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\RenderedTreeEditor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\RenderedElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RenderedTreeEditor::class)]
class RenderedTreeEditorTest extends TestCase
{
    #[TestDox('maps every element of a multi-root forest at every depth')]
    public function testMapNodesReachesEveryElement(): void
    {
        $tree = [
            RenderedElementBuilder::create('core:section', 'root-1')
                ->withSlot('main', [
                    RenderedElementBuilder::create('core:section', 'child-1')
                        ->withSlot('inner', [
                            RenderedElementBuilder::create('core:text', 'grandchild-1')->build(),
                            RenderedElementBuilder::create('core:text', 'grandchild-2')->build(),
                        ])
                        ->build(),
                    RenderedElementBuilder::create('core:text', 'child-2')->build(),
                ])
                ->build(),
            RenderedElementBuilder::create('core:text', 'root-2')->build(),
        ];

        static::assertCount(2, $tree[0]->slots['main']);
        static::assertCount(2, $tree[0]->slots['main'][0]->slots['inner']);

        $mapped = (new RenderedTreeEditor())->mapNodes(
            $tree,
            static fn (RenderedElement $element): RenderedElement => $element->withProperty('marked', true)
        );

        static::assertTrue($mapped[0]->properties['marked']);
        static::assertTrue($mapped[0]->slots['main'][0]->properties['marked']);
        static::assertTrue($mapped[0]->slots['main'][1]->properties['marked']);
        static::assertTrue($mapped[0]->slots['main'][0]->slots['inner'][0]->properties['marked']);
        static::assertTrue($mapped[0]->slots['main'][0]->slots['inner'][1]->properties['marked']);
        static::assertTrue($mapped[1]->properties['marked']);
    }

    #[TestDox('rebuilds rather than editing in place, so the elements it was given keep their own field values')]
    public function testMapNodesLeavesTheInputElementFieldsUntouched(): void
    {
        $root = RenderedElementBuilder::create('core:section', 'root-1')
            ->withSlot('main', [RenderedElementBuilder::create('core:text', 'child-1')->build()])
            ->build();

        $mapped = (new RenderedTreeEditor())->mapNodes(
            [$root],
            static fn (RenderedElement $element): RenderedElement => $element->withProperty('marked', true)
        );

        static::assertNotSame($root, $mapped[0]);
        static::assertSame([], $root->properties);
        static::assertSame([], $root->slots['main'][0]->properties);
    }

    #[TestDox('carries an object property value through the rebuild by identity, so a mapper mutating one reaches the input too')]
    public function testMapNodesSharesAnObjectPropertyValueWithTheInput(): void
    {
        $struct = new StubStruct();
        $root = RenderedElementBuilder::create('core:image', 'root-1')
            ->withProperty('media', $struct)
            ->build();

        static::assertSame($struct, $root->properties['media']);

        $mapped = (new RenderedTreeEditor())->mapNodes(
            [$root],
            static fn (RenderedElement $element): RenderedElement => $element->withProperty('marked', true)
        );

        static::assertSame($struct, $mapped[0]->properties['media']);
    }

    #[TestDox('hands the mapper a parent whose slot children the mapper has already seen')]
    public function testMapNodesMapsChildrenBeforeTheirParent(): void
    {
        $root = RenderedElementBuilder::create('core:section', 'root-1')
            ->withSlot('main', [
                RenderedElementBuilder::create('core:text', 'child-1')->build(),
                RenderedElementBuilder::create('core:text', 'child-2')->build(),
            ])
            ->build();

        static::assertCount(2, $root->slots['main']);

        $mapped = (new RenderedTreeEditor())->mapNodes([$root], static function (RenderedElement $element): RenderedElement {
            $childTags = array_map(
                static fn (RenderedElement $child): mixed => $child->properties['tag'] ?? null,
                $element->slots['main'] ?? []
            );

            return $element->withProperty('tag', 'tagged')->withProperty('childTags', $childTags);
        });

        static::assertSame(['tagged', 'tagged'], $mapped[0]->properties['childTags']);
    }

    #[TestDox('keeps the element a mapper returns rather than rebuilding it afterwards')]
    public function testMapNodesKeepsWhatTheMapperReturns(): void
    {
        $replacement = RenderedElementBuilder::create('core:text', 'replacement-1')->build();
        $root = RenderedElementBuilder::create('core:section', 'root-1')
            ->withSlot('main', [RenderedElementBuilder::create('core:text', 'child-1')->build()])
            ->build();

        $mapped = (new RenderedTreeEditor())->mapNodes(
            [$root],
            static fn (RenderedElement $element): RenderedElement => $element->id === 'root-1' ? $replacement : $element
        );

        static::assertSame([$replacement], $mapped);
    }

    #[TestDox('returns an empty forest for an empty forest')]
    public function testMapNodesOnAnEmptyForest(): void
    {
        $mapped = (new RenderedTreeEditor())->mapNodes(
            [],
            static fn (RenderedElement $element): RenderedElement => $element->withProperty('marked', true)
        );

        static::assertSame([], $mapped);
    }

    #[TestDox('does not map an element the mapper itself introduced')]
    public function testMapNodesDoesNotRevisitAnIntroducedElement(): void
    {
        $introduced = RenderedElementBuilder::create('core:text', 'introduced-1')->build();

        $mapped = (new RenderedTreeEditor())->mapNodes(
            [RenderedElementBuilder::create('core:section', 'root-1')->build()],
            static fn (RenderedElement $element): RenderedElement => $element
                ->withProperty('marked', true)
                ->withSlots(['main' => [$introduced]])
        );

        static::assertSame($introduced, $mapped[0]->slots['main'][0]);
        static::assertSame([], $mapped[0]->slots['main'][0]->properties);
    }
}
