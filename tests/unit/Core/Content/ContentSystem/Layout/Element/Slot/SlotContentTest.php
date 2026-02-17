<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Slot;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SlotContent::class)]
class SlotContentTest extends TestCase
{
    #[TestDox('yields all added elements during iteration')]
    public function testIterationYieldsAllElements(): void
    {
        $elementA = ContentElementBuilder::create('component-a')->build();
        $elementB = ContentElementBuilder::create('component-b')->build();

        $slot = new SlotContent();
        $slot->add($elementA);
        $slot->add($elementB);

        $iterated = [];
        foreach ($slot as $element) {
            $iterated[] = $element;
        }

        static::assertCount(2, $iterated);
        static::assertSame($elementA, $iterated[0]);
        static::assertSame($elementB, $iterated[1]);
    }

    #[TestDox('returns the number of added elements')]
    public function testCountReturnsElementCount(): void
    {
        $slot = new SlotContent();
        $slot->add(ContentElementBuilder::create('component-a')->build());
        $slot->add(ContentElementBuilder::create('component-b')->build());
        $slot->add(ContentElementBuilder::create('component-c')->build());

        static::assertCount(3, $slot);
    }
}
