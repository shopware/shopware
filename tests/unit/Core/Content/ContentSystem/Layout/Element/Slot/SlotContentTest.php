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
    #[TestDox('stores elements with correct count and iteration order')]
    public function testAddStoresElementsWithCorrectCountAndIteration(): void
    {
        $elementA = ContentElementBuilder::create('component-a')->build();
        $elementB = ContentElementBuilder::create('component-b')->build();
        $elementC = ContentElementBuilder::create('component-c')->build();

        $slot = new SlotContent();
        $slot->add($elementA);
        $slot->add($elementB);
        $slot->add($elementC);

        static::assertCount(3, $slot);

        $iterated = [];
        foreach ($slot as $element) {
            $iterated[] = $element;
        }

        static::assertSame($elementA, $iterated[0]);
        static::assertSame($elementB, $iterated[1]);
        static::assertSame($elementC, $iterated[2]);
    }
}
