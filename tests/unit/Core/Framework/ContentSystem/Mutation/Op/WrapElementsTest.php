<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\WrapElements;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WrapElements::class)]
class WrapElementsTest extends TestCase
{
    #[TestDox('wraps root siblings into a freshly minted container at the first target position, preserving slot order and reporting the container and wrapped ids as affected')]
    public function testWrapMovesSiblingsIntoContainer(): void
    {
        $tree = new StoredTree([
            new StoredElement('x', 'Sw:Block'),
            new StoredElement('a', 'Sw:Block'),
            new StoredElement('b', 'Sw:Block'),
            new StoredElement('y', 'Sw:Block'),
        ]);

        $wrap = new WrapElements($this->registry('Sw:Container'), ['b', 'a'], 'Sw:Container', 'content');
        $result = $wrap->apply($tree);

        static::assertCount(3, $result->roots);
        static::assertSame(['x', 'Sw:Container', 'y'], [$result->roots[0]->id, $result->roots[1]->component, $result->roots[2]->id]);
        static::assertSame(['a', 'b'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots[1]->slots['content']));
        static::assertSame([$result->roots[1]->id, 'b', 'a'], $wrap->affected());
    }

    #[TestDox('wraps nested siblings inside their parent slot')]
    public function testWrapNestedSiblings(): void
    {
        $tree = new StoredTree([new StoredElement('parent', 'Sw:Block', [], [], [
            'content' => [new StoredElement('a', 'Sw:Block'), new StoredElement('b', 'Sw:Block')],
        ])]);

        $result = (new WrapElements($this->registry('Sw:Container'), ['a', 'b'], 'Sw:Container', 'items'))->apply($tree);

        $parentChildren = $result->roots[0]->slots['content'];
        static::assertCount(1, $parentChildren);
        static::assertSame('Sw:Container', $parentChildren[0]->component);
        static::assertSame(['a', 'b'], array_map(static fn (StoredElement $e): string => $e->id, $parentChildren[0]->slots['items']));
    }

    #[TestDox('rejects wrapping an empty target list with a 400')]
    public function testWrapEmptyTargetsRejected(): void
    {
        $wrap = new WrapElements($this->registry('Sw:Container'), [], 'Sw:Container', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationInvalidWrapTargets('at least one element is required'));
        $wrap->apply(new StoredTree([new StoredElement('a', 'Sw:Block')]));
    }

    #[TestDox('rejects wrapping non-sibling elements with a 400')]
    public function testWrapNonSiblingsRejected(): void
    {
        $tree = new StoredTree([
            new StoredElement('a', 'Sw:Block'),
            new StoredElement('parent', 'Sw:Block', [], [], [
                'content' => [new StoredElement('b', 'Sw:Block')],
            ]),
        ]);

        $wrap = new WrapElements($this->registry('Sw:Container'), ['a', 'b'], 'Sw:Container', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationInvalidWrapTargets('they must be siblings in one slot'));
        $wrap->apply($tree);
    }

    #[TestDox('rejects an unregistered container type with a 400')]
    public function testWrapUnknownContainerTypeRejected(): void
    {
        $tree = new StoredTree([new StoredElement('a', 'Sw:Block')]);

        $wrap = new WrapElements($this->registry('Sw:Container'), ['a'], 'Sw:Ghost', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('Sw:Ghost'));
        $wrap->apply($tree);
    }

    #[TestDox('rejects wrapping without a container slot with a 400')]
    public function testWrapWithoutSlotRejected(): void
    {
        $tree = new StoredTree([new StoredElement('a', 'Sw:Block')]);

        $wrap = new WrapElements($this->registry('Sw:Container'), ['a'], 'Sw:Container', null);

        $this->expectExceptionObject(ContentSystemException::mutationSlotRequired());
        $wrap->apply($tree);
    }

    #[TestDox('rejects wrapping when a target is missing from the tree with a 400')]
    public function testWrapMissingElementRejected(): void
    {
        $tree = new StoredTree([new StoredElement('a', 'Sw:Block')]);

        $wrap = new WrapElements($this->registry('Sw:Container'), ['a', 'ghost'], 'Sw:Container', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $wrap->apply($tree);
    }

    #[TestDox('rejects wrapping elements that share a parent but sit in different slots with a 400')]
    public function testWrapSameParentDifferentSlotRejected(): void
    {
        $tree = new StoredTree([new StoredElement('parent', 'Sw:Block', [], [], [
            'left' => [new StoredElement('a', 'Sw:Block')],
            'right' => [new StoredElement('b', 'Sw:Block')],
        ])]);

        $wrap = new WrapElements($this->registry('Sw:Container'), ['a', 'b'], 'Sw:Container', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationInvalidWrapTargets('they must be siblings in one slot'));
        $wrap->apply($tree);
    }

    #[TestDox('rejects wrapping the same element id twice with a 400')]
    public function testWrapDuplicateTargetsRejected(): void
    {
        $wrap = new WrapElements($this->registry('Sw:Container'), ['a', 'a'], 'Sw:Container', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationInvalidWrapTargets('they must be distinct'));
        $wrap->apply(new StoredTree([new StoredElement('a', 'Sw:Block'), new StoredElement('b', 'Sw:Block')]));
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
