<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElementLowering;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\RenderedElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * Each fixture pair is deliberately asymmetric: the stored and the rendered side of one element carry
 * property maps that share no key, so a produced property map identifies which side it was read from.
 * Production pairs the two forests by element id and drives the walk off the rendered side, so a fixture
 * pair only has to agree on ids — the rendered forest may hold fewer roots than the stored one, and in a
 * different order.
 *
 * `lowerTree()` is the only public entry point, so a single-element case goes through it too.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentElementLowering::class)]
class ContentElementLoweringTest extends TestCase
{
    private ContentElementLowering $lowering;

    protected function setUp(): void
    {
        $this->lowering = new ContentElementLowering();
    }

    #[TestDox('reads the property map off the rendered element and never off the stored one')]
    public function testPropertiesComeFromTheRenderedElement(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'text-element')
            ->withProperties(['headline' => 'stored-headline', 'storedOnly' => 'stored-value'])
            ->build();
        $rendered = RenderedElementBuilder::create('Sw:Text', 'text-element')
            ->withProperties(['headline' => 'rendered-headline', 'renderedOnly' => 'rendered-value'])
            ->build();

        $element = $this->lowerOne($stored, $rendered);

        // Whole-map comparison: that `storedOnly` is absent is as much the claim as that `headline` holds
        // the rendered value.
        static::assertSame(
            ['headline' => 'rendered-headline', 'renderedOnly' => 'rendered-value'],
            $element->getProperties()
        );
    }

    #[TestDox('reads the wiring and the attribution off the stored element')]
    public function testWiringAndAttributionComeFromTheStoredElement(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'text-element')
            ->withDataRequirement('language', 'language', new LanguageLoaderConfig())
            ->withProvider('language', BroadcastDistributionConfig::simple())
            ->withConsumer('product', ContextType::Single)
            ->withAttributedSpecification('product', 'Sw:Text:product-binding')
            ->build();
        $rendered = RenderedElementBuilder::create('Sw:Text', 'text-element')->build();

        $element = $this->lowerOne($stored, $rendered);

        static::assertSame($stored->dataRequirements, $element->getDataRequirements());
        static::assertSame($stored->contextDefinitions, $element->getContextDefinitions());
        static::assertSame($stored->attributedSpecifications, $element->getAttributedSpecifications());
    }

    #[TestDox('reads the style off the stored element, the one field both models carry')]
    public function testStyleComesFromTheStoredElement(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'text-element')
            ->withStyle(new ElementStyle(['col-span' => ['md' => 6]]))
            ->build();
        $rendered = RenderedElementBuilder::create('Sw:Text', 'text-element')
            ->withStyle(new ElementStyle(['col-span' => ['md' => 12]]))
            ->build();

        $element = $this->lowerOne($stored, $rendered);

        // Whole-map comparison against the stored values: both sides carry a `col-span`, so only the value
        // says which side it was read from.
        static::assertSame(['col-span' => ['md' => 6]], $element->getStyle()->toArray());
    }

    #[TestDox('pairs the two forests by element id, so each root keeps its own rendered property map')]
    public function testLowerTreePairsTheForestsById(): void
    {
        $stored = [
            StoredElementBuilder::create('Sw:Text', 'first-root')->build(),
            StoredElementBuilder::create('Sw:Text', 'second-root')->build(),
        ];
        // The rendered forest arrives in the opposite order to the stored one, and the two roots differ in
        // both id and rendered property value. Under the positional pairing production used before the
        // finishing steps moved ahead of this bridge, each root would come back wearing the other root's
        // property map, so this test fails on that implementation.
        $rendered = [
            RenderedElementBuilder::create('Sw:Text', 'second-root')
                ->withProperty('headline', 'second-root-headline')
                ->build(),
            RenderedElementBuilder::create('Sw:Text', 'first-root')
                ->withProperty('headline', 'first-root-headline')
                ->build(),
        ];

        $elements = $this->lowering->lowerTree($stored, $rendered);

        // Result order follows the rendered forest, because that is the order the finishing steps decided.
        static::assertCount(2, $elements);
        static::assertSame('second-root', $elements[0]->getId());
        static::assertSame(['headline' => 'second-root-headline'], $elements[0]->getProperties());
        static::assertSame('first-root', $elements[1]->getId());
        static::assertSame(['headline' => 'first-root-headline'], $elements[1]->getProperties());
    }

    #[TestDox('lowers a rendered forest holding one stored child as its only root, the shape the partial extract produces')]
    public function testLowerTreeLowersARenderedForestReducedToAStoredChild(): void
    {
        $storedChild = StoredElementBuilder::create('Sw:Text', 'child-element')
            ->withAttributedSpecification('product', 'Sw:Text:product-binding')
            ->build();
        $stored = [
            StoredElementBuilder::create('Sw:Section', 'section-element')
                ->withSlot('main', [$storedChild])
                ->build(),
        ];
        // What the partial extract hands the bridge: a single-root forest whose root was a stored CHILD,
        // with the stored root it came from nowhere in it. Only an index into the stored forest can pair
        // this; a walk in step would read the section's fields onto the child.
        $rendered = [
            RenderedElementBuilder::create('Sw:Text', 'child-element')
                ->withProperty('headline', 'child-headline')
                ->build(),
        ];

        $elements = $this->lowering->lowerTree($stored, $rendered);

        static::assertCount(1, $elements);
        static::assertSame('child-element', $elements[0]->getId());
        static::assertSame(['headline' => 'child-headline'], $elements[0]->getProperties());
        static::assertSame($storedChild->attributedSpecifications, $elements[0]->getAttributedSpecifications());
    }

    #[TestDox('rejects a rendered element whose id is in no stored element')]
    public function testLowerTreeRejectsARenderedIdTheStoredForestDoesNotHold(): void
    {
        $stored = [StoredElementBuilder::create('Sw:Text', 'stored-element')->build()];
        $rendered = [RenderedElementBuilder::create('Sw:Text', 'unknown-element')->build()];

        $this->expectExceptionObject(ContentSystemException::invalidMapValue(
            'Stored element index',
            'unknown-element',
            StoredElement::class,
            'no such stored element'
        ));

        $this->lowering->lowerTree($stored, $rendered);
    }

    #[TestDox('rejects a stored forest that repeats an element id in two slots')]
    public function testLowerTreeRejectsAStoredForestRepeatingAnElementId(): void
    {
        // The two occurrences carry DIFFERENT components, so an index that let the last one win would hand
        // both rendered occurrences the second element's fields rather than each its own. Reachable from a
        // raw-SQL or migration write, or from a listener replacing the tree: neither runs tree validation.
        $stored = [
            StoredElementBuilder::create('Sw:Section', 'section-element')
                ->withSlot('left', [StoredElementBuilder::create('Sw:Text', 'repeated-id')->build()])
                ->withSlot('right', [StoredElementBuilder::create('Sw:Media:Image', 'repeated-id')->build()])
                ->build(),
        ];
        $rendered = [RenderedElementBuilder::create('Sw:Section', 'section-element')->build()];

        $this->expectExceptionObject(ContentSystemException::duplicateElementId('repeated-id'));

        $this->lowering->lowerTree($stored, $rendered);
    }

    #[TestDox('rejects a rendered forest that repeats an element id in one slot')]
    public function testLowerTreeRejectsARenderedForestRepeatingAnElementId(): void
    {
        // Reachable from a finalization listener replacing the rendered tree: nothing between that
        // replacement and this bridge validates the forest. The stored side repeats nothing, so the rendered
        // pre-pass is the only guard that can fail this call, and the two rendered occurrences carry DIFFERENT
        // property values, so a bridge that merely counted its results could not pass by accident.
        $stored = [
            StoredElementBuilder::create('Sw:Section', 'section-element')
                ->withSlot('main', [StoredElementBuilder::create('Sw:Text', 'repeated-id')->build()])
                ->build(),
        ];
        $rendered = [
            RenderedElementBuilder::create('Sw:Section', 'section-element')
                ->withSlot('main', [
                    RenderedElementBuilder::create('Sw:Text', 'repeated-id')
                        ->withProperty('headline', 'first-headline')
                        ->build(),
                    RenderedElementBuilder::create('Sw:Text', 'repeated-id')
                        ->withProperty('headline', 'second-headline')
                        ->build(),
                ])
                ->build(),
        ];

        $this->expectExceptionObject(ContentSystemException::duplicateElementId('repeated-id'));

        $this->lowering->lowerTree($stored, $rendered);
    }

    #[TestDox('pairs each child with its own rendered counterpart under the surviving slot name')]
    public function testEachChildKeepsItsOwnRenderedProperties(): void
    {
        $stored = StoredElementBuilder::create('Sw:Section', 'section-element')
            ->withSlot('main', [
                StoredElementBuilder::create('Sw:Text', 'child-first')->build(),
                StoredElementBuilder::create('Sw:Text', 'child-second')->build(),
            ])
            ->build();
        $rendered = RenderedElementBuilder::create('Sw:Section', 'section-element')
            ->withSlot('main', [
                RenderedElementBuilder::create('Sw:Text', 'child-first')
                    ->withProperty('headline', 'first-headline')
                    ->build(),
                RenderedElementBuilder::create('Sw:Text', 'child-second')
                    ->withProperty('headline', 'second-headline')
                    ->build(),
            ])
            ->build();

        $element = $this->lowerOne($stored, $rendered);

        static::assertSame(['main'], array_keys($element->getSlots()));
        $mainSlot = $element->getSlots()['main'] ?? null;
        static::assertInstanceOf(SlotContent::class, $mainSlot);
        static::assertCount(2, $mainSlot);

        $first = $mainSlot->first();
        static::assertInstanceOf(ContentElement::class, $first);
        static::assertSame('child-first', $first->getId());
        static::assertSame(['headline' => 'first-headline'], $first->getProperties());

        $second = $mainSlot->last();
        static::assertInstanceOf(ContentElement::class, $second);
        static::assertSame('child-second', $second->getId());
        static::assertSame(['headline' => 'second-headline'], $second->getProperties());
    }

    #[TestDox('walks the whole tree, so a grandchild reaches its own rendered properties')]
    public function testTheWalkReachesAGrandchild(): void
    {
        $storedGrandchild = StoredElementBuilder::create('Sw:Text', 'grandchild-element')
            ->withProperty('headline', 'stored-grandchild-headline')
            ->build();
        $stored = StoredElementBuilder::create('Sw:Section', 'section-element')
            ->withSlot('main', [
                StoredElementBuilder::create('Sw:Container', 'child-element')
                    ->withSlot('inner', [$storedGrandchild])
                    ->build(),
            ])
            ->build();

        $renderedGrandchild = RenderedElementBuilder::create('Sw:Text', 'grandchild-element')
            ->withProperty('headline', 'rendered-grandchild-headline')
            ->build();
        $rendered = RenderedElementBuilder::create('Sw:Section', 'section-element')
            ->withSlot('main', [
                RenderedElementBuilder::create('Sw:Container', 'child-element')
                    ->withSlot('inner', [$renderedGrandchild])
                    ->build(),
            ])
            ->build();

        $element = $this->lowerOne($stored, $rendered);

        $grandchild = $this->onlyChildIn($this->onlyChildIn($element, 'main'), 'inner');
        static::assertSame('grandchild-element', $grandchild->getId());
        static::assertSame(['headline' => 'rendered-grandchild-headline'], $grandchild->getProperties());
    }

    private function lowerOne(StoredElement $stored, RenderedElement $rendered): ContentElement
    {
        $element = $this->lowering->lowerTree([$stored], [$rendered])[0] ?? null;
        static::assertInstanceOf(ContentElement::class, $element);

        return $element;
    }

    private function onlyChildIn(ContentElement $element, string $slot): ContentElement
    {
        $slotContent = $element->getSlots()[$slot] ?? null;
        static::assertInstanceOf(SlotContent::class, $slotContent);
        static::assertCount(1, $slotContent);

        $child = $slotContent->first();
        static::assertInstanceOf(ContentElement::class, $child);

        return $child;
    }
}
