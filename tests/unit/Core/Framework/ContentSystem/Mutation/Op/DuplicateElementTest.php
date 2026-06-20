<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(DuplicateElement::class)]
class DuplicateElementTest extends TestCase
{
    #[TestDox('inserts the clone as the next sibling with a fresh id')]
    public function testDuplicateInsertsCloneAsNextSibling(): void
    {
        $tree = [new ContentElement('original', 'Sw:Card'), new ContentElement('other', 'Sw:Block')];

        $result = (new DuplicateElement('original'))->apply($tree);

        static::assertCount(3, $result);
        static::assertSame('original', $result[0]->getId());
        static::assertSame('Sw:Card', $result[1]->getComponent());
        static::assertNotSame('original', $result[1]->getId());
        static::assertSame('other', $result[2]->getId());
    }

    #[TestDox('remints every id in the cloned subtree')]
    public function testDuplicateRemintsEverySubtreeId(): void
    {
        $tree = [new ContentElement('root', 'Sw:Block', [], [], [
            'content' => new SlotContent([new ContentElement('child', 'Sw:Block')]),
        ])];

        $result = (new DuplicateElement('root'))->apply($tree);

        $clone = $result[1];
        $clonedChild = array_values($clone->getSlots()['content']->getElements())[0];
        static::assertNotSame('root', $clone->getId());
        static::assertNotSame('child', $clonedChild->getId());
        static::assertSame('Sw:Block', $clonedChild->getComponent());
    }

    #[TestDox('reports the cloned subtree ids as affected')]
    public function testDuplicateAffectedAreCloneIds(): void
    {
        $tree = [new ContentElement('root', 'Sw:Block', [], [], [
            'content' => new SlotContent([new ContentElement('child', 'Sw:Block')]),
        ])];

        $duplicate = new DuplicateElement('root');
        $result = $duplicate->apply($tree);

        $clone = $result[1];
        $clonedChild = array_values($clone->getSlots()['content']->getElements())[0];
        static::assertSame([$clone->getId(), $clonedChild->getId()], $duplicate->affected());
    }

    #[TestDox('carries key-based wiring over to the clone unchanged')]
    public function testDuplicatePreservesKeyBasedWiring(): void
    {
        $requirement = new DataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $contextDefinitions = new ContextDefinitions(['list' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())], []);
        $tree = [new ContentElement('original', 'Sw:Card', ['product' => $requirement], [], [], $contextDefinitions)];

        $result = (new DuplicateElement('original'))->apply($tree);

        static::assertSame(['product' => $requirement], $result[1]->getDataRequirements());
        static::assertSame($contextDefinitions, $result[1]->getContextDefinitions());
    }

    #[TestDox('duplicates a nested element into the same parent slot')]
    public function testDuplicateNestedElement(): void
    {
        $tree = [new ContentElement('parent', 'Sw:Block', [], [], [
            'content' => new SlotContent([new ContentElement('original', 'Sw:Card')]),
        ])];

        $result = (new DuplicateElement('original'))->apply($tree);

        $children = array_values($result[0]->getSlots()['content']->getElements());
        static::assertCount(2, $children);
        static::assertSame('original', $children[0]->getId());
        static::assertTrue(Uuid::isValid($children[1]->getId()));
        static::assertSame('Sw:Card', $children[1]->getComponent());
    }

    #[TestDox('inserts the clone at an explicit index when given')]
    public function testDuplicateAtExplicitIndex(): void
    {
        $tree = [new ContentElement('original', 'Sw:Card'), new ContentElement('other', 'Sw:Block')];

        $result = (new DuplicateElement('original', 0))->apply($tree);

        static::assertSame('Sw:Card', $result[0]->getComponent());
        static::assertSame('original', $result[1]->getId());
        static::assertSame('other', $result[2]->getId());
    }

    #[TestDox('rejects duplicating an element absent from the tree with a 400')]
    public function testDuplicateMissingElementRejected(): void
    {
        $duplicate = new DuplicateElement('ghost');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $duplicate->apply([new ContentElement('other', 'Sw:Block')]);
    }

    #[TestDox('does not mutate the input parent in place when duplicating a nested child')]
    public function testDuplicateDoesNotMutateInput(): void
    {
        $parent = new ContentElement('parent', 'Sw:Block', [], [], [
            'content' => new SlotContent([new ContentElement('original', 'Sw:Card')]),
        ]);

        (new DuplicateElement('original'))->apply([$parent]);

        static::assertCount(1, $parent->getSlots()['content']->getElements());
    }
}
