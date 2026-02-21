<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Slot;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;

/**
 * @internal
 */
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

        $iterated = [];
        foreach ($slot as $element) {
            $iterated[] = $element;
        }

        static::assertSame($elementA, $iterated[0]);
        static::assertSame($elementB, $iterated[1]);
        static::assertSame($elementC, $iterated[2]);
    }

    #[TestDox('throws when adding an element of the wrong type')]
    public function testThrowsWhenAddingWrongType(): void
    {
        $slot = new SlotContent();

        static::expectExceptionObject(FrameworkException::collectionElementInvalidType(ContentElement::class, \stdClass::class));

        $slot->add(new \stdClass()); // @phpstan-ignore argument.type
    }
}
