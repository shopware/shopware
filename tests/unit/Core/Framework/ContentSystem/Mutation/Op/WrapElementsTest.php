<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\WrapElements;

/**
 * @internal
 */
#[CoversClass(WrapElements::class)]
class WrapElementsTest extends TestCase
{
    use AssertsImmutableInput;

    #[TestDox('wraps root siblings into a freshly minted container at the first target position, preserving slot order and reporting the container and wrapped ids as affected')]
    public function testWrapMovesSiblingsIntoContainer(): void
    {
        $tree = [
            new ContentElement('x', 'Sw:Block'),
            new ContentElement('a', 'Sw:Block'),
            new ContentElement('b', 'Sw:Block'),
            new ContentElement('y', 'Sw:Block'),
        ];

        $wrap = new WrapElements($this->registry('Sw:Container'), ['b', 'a'], 'Sw:Container', 'content');
        $result = $wrap->apply($tree);

        static::assertCount(3, $result);
        static::assertSame(['x', 'Sw:Container', 'y'], [$result[0]->getId(), $result[1]->getComponent(), $result[2]->getId()]);
        $wrapped = array_values($result[1]->getSlots()['content']->getElements());
        static::assertSame(['a', 'b'], array_map(static fn (ContentElement $e): string => $e->getId(), $wrapped));
        static::assertSame([$result[1]->getId(), 'b', 'a'], $wrap->affected());
    }

    #[TestDox('wraps nested siblings inside their parent slot without mutating the input tree')]
    public function testWrapNestedSiblings(): void
    {
        $tree = [new ContentElement('parent', 'Sw:Block', [], ['title' => 'Section'], [
            'content' => new SlotContent([new ContentElement('a', 'Sw:Block'), new ContentElement('b', 'Sw:Block')]),
        ])];
        $before = $this->snapshotTree($tree);

        $result = (new WrapElements($this->registry('Sw:Container'), ['a', 'b'], 'Sw:Container', 'items'))->apply($tree);

        $parentChildren = array_values($result[0]->getSlots()['content']->getElements());
        static::assertCount(1, $parentChildren);
        static::assertSame('Sw:Container', $parentChildren[0]->getComponent());
        $wrapped = array_values($parentChildren[0]->getSlots()['items']->getElements());
        static::assertSame(['a', 'b'], array_map(static fn (ContentElement $e): string => $e->getId(), $wrapped));
        $this->assertInputTreeUnmutated($before, $tree);
    }

    #[TestDox('rejects wrapping non-sibling elements with a 400')]
    public function testWrapNonSiblingsRejected(): void
    {
        $tree = [
            new ContentElement('a', 'Sw:Block'),
            new ContentElement('parent', 'Sw:Block', [], [], [
                'content' => new SlotContent([new ContentElement('b', 'Sw:Block')]),
            ]),
        ];

        $wrap = new WrapElements($this->registry('Sw:Container'), ['a', 'b'], 'Sw:Container', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationInvalidWrapTargets('they must be siblings in one slot'));
        $wrap->apply($tree);
    }

    #[TestDox('rejects an unregistered container type with a 400')]
    public function testWrapUnknownContainerTypeRejected(): void
    {
        $tree = [new ContentElement('a', 'Sw:Block')];

        $wrap = new WrapElements($this->registry('Sw:Container'), ['a'], 'Sw:Ghost', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('Sw:Ghost'));
        $wrap->apply($tree);
    }

    #[TestDox('rejects wrapping without a container slot with a 400')]
    public function testWrapWithoutSlotRejected(): void
    {
        $tree = [new ContentElement('a', 'Sw:Block')];

        $wrap = new WrapElements($this->registry('Sw:Container'), ['a'], 'Sw:Container', null);

        $this->expectExceptionObject(ContentSystemException::mutationSlotRequired());
        $wrap->apply($tree);
    }

    #[TestDox('rejects wrapping when a target is missing from the tree with a 400')]
    public function testWrapMissingElementRejected(): void
    {
        $tree = [new ContentElement('a', 'Sw:Block')];

        $wrap = new WrapElements($this->registry('Sw:Container'), ['a', 'ghost'], 'Sw:Container', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $wrap->apply($tree);
    }

    #[TestDox('rejects wrapping an empty target list with a 400')]
    public function testWrapEmptyTargetsRejected(): void
    {
        $wrap = new WrapElements($this->registry('Sw:Container'), [], 'Sw:Container', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationInvalidWrapTargets('at least one element is required'));
        $wrap->apply([new ContentElement('a', 'Sw:Block')]);
    }

    #[TestDox('rejects wrapping elements that share a parent but sit in different slots with a 400')]
    public function testWrapSameParentDifferentSlotRejected(): void
    {
        $tree = [new ContentElement('parent', 'Sw:Block', [], [], [
            'left' => new SlotContent([new ContentElement('a', 'Sw:Block')]),
            'right' => new SlotContent([new ContentElement('b', 'Sw:Block')]),
        ])];

        $wrap = new WrapElements($this->registry('Sw:Container'), ['a', 'b'], 'Sw:Container', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationInvalidWrapTargets('they must be siblings in one slot'));
        $wrap->apply($tree);
    }

    #[TestDox('rejects wrapping the same element id twice with a 400')]
    public function testWrapDuplicateTargetsRejected(): void
    {
        $wrap = new WrapElements($this->registry('Sw:Container'), ['a', 'a'], 'Sw:Container', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationInvalidWrapTargets('they must be distinct'));
        $wrap->apply([new ContentElement('a', 'Sw:Block'), new ContentElement('b', 'Sw:Block')]);
    }

    private function registry(string $type): AbstractContentSystemElementTypeRegistry
    {
        $spec = new ContentSystemElementTypeSpecification($type, $type, '', null, null, new CopilotSpecification('', []), [], []);
        $specs = [$type => $spec];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return $registry;
    }
}
