<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElementLowering;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\RenderedElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * Each fixture pair is deliberately asymmetric: the stored and the rendered side of one element carry
 * property maps that share no key, so a produced property map identifies which side it was read from.
 * Production pairs the two forests positionally, so the pairs here are built in matching shape by hand.
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

        $element = $this->lowering->lower($stored, $rendered);

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

        $element = $this->lowering->lower($stored, $rendered);

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

        $element = $this->lowering->lower($stored, $rendered);

        // Whole-map comparison against the stored values: both sides carry a `col-span`, so only the value
        // says which side it was read from.
        static::assertSame(['col-span' => ['md' => 6]], $element->getStyle()->toArray());
    }

    #[TestDox('pairs the two forests positionally, so each root keeps its own rendered property map')]
    public function testLowerTreePairsTheForestsPositionally(): void
    {
        $stored = [
            StoredElementBuilder::create('Sw:Text', 'first-root')->build(),
            StoredElementBuilder::create('Sw:Text', 'second-root')->build(),
        ];
        $rendered = [
            RenderedElementBuilder::create('Sw:Text', 'first-root')
                ->withProperty('headline', 'first-root-headline')
                ->build(),
            RenderedElementBuilder::create('Sw:Text', 'second-root')
                ->withProperty('headline', 'second-root-headline')
                ->build(),
        ];

        $elements = $this->lowering->lowerTree($stored, $rendered);

        // The two roots differ in both id and rendered properties, so a swapped pairing shows up as a root
        // wearing the other root's property map rather than as an equivalent result.
        static::assertCount(2, $elements);
        static::assertSame('first-root', $elements[0]->getId());
        static::assertSame(['headline' => 'first-root-headline'], $elements[0]->getProperties());
        static::assertSame('second-root', $elements[1]->getId());
        static::assertSame(['headline' => 'second-root-headline'], $elements[1]->getProperties());
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

        $element = $this->lowering->lower($stored, $rendered);

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

        $element = $this->lowering->lower($stored, $rendered);

        $grandchild = $this->onlyChildIn($this->onlyChildIn($element, 'main'), 'inner');
        static::assertSame('grandchild-element', $grandchild->getId());
        static::assertSame(['headline' => 'rendered-grandchild-headline'], $grandchild->getProperties());
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
