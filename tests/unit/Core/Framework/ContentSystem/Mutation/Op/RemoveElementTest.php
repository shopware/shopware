<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;

/**
 * @internal
 */
#[CoversClass(RemoveElement::class)]
class RemoveElementTest extends TestCase
{
    #[TestDox('deletes the element together with its whole subtree')]
    public function testRemoveDeletesElementAndSubtree(): void
    {
        $tree = [
            new ContentElement('keep', 'Sw:Block'),
            new ContentElement('drop', 'Sw:Block', [], [], [
                'content' => new SlotContent([new ContentElement('child', 'Sw:Block')]),
            ]),
        ];

        $result = (new RemoveElement('drop'))->apply($tree);

        static::assertCount(1, $result);
        static::assertSame('keep', $result[0]->getId());
    }

    #[TestDox('removes a nested element while keeping its siblings')]
    public function testRemoveNestedElementKeepsSiblings(): void
    {
        $parent = new ContentElement('parent', 'Sw:Block', [], [], [
            'content' => new SlotContent([
                new ContentElement('a', 'Sw:Block'),
                new ContentElement('b', 'Sw:Block'),
            ]),
        ]);

        $result = (new RemoveElement('a'))->apply([$parent]);

        $children = array_values($result[0]->getSlots()['content']->getElements());
        static::assertSame(['b'], array_map(static fn (ContentElement $e): string => $e->getId(), $children));
    }

    #[TestDox('leaves a surviving element data requirements untouched')]
    public function testRemoveLeavesSurvivorWiringUntouched(): void
    {
        $requirement = new DataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $survivor = new ContentElement('survivor', 'Sw:Block', ['product' => $requirement]);
        $tree = [$survivor, new ContentElement('drop', 'Sw:Block')];

        $result = (new RemoveElement('drop'))->apply($tree);

        static::assertSame(['product' => $requirement], $result[0]->getDataRequirements());
    }

    #[TestDox('reports no affected elements because downward-only context flow strands no survivor')]
    public function testRemoveAffectedIsEmpty(): void
    {
        $remove = new RemoveElement('drop');
        $remove->apply([new ContentElement('drop', 'Sw:Block'), new ContentElement('keep', 'Sw:Block')]);

        static::assertSame([], $remove->affected());
    }

    #[TestDox('rejects removing an element absent from the tree with a 400')]
    public function testRemoveMissingElementRejected(): void
    {
        $remove = new RemoveElement('ghost');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $remove->apply([new ContentElement('other', 'Sw:Block')]);
    }

    #[TestDox('does not mutate the input parent in place when removing a nested child')]
    public function testRemoveDoesNotMutateInput(): void
    {
        $parent = new ContentElement('parent', 'Sw:Block', [], [], [
            'content' => new SlotContent([new ContentElement('a', 'Sw:Block'), new ContentElement('b', 'Sw:Block')]),
        ]);

        (new RemoveElement('a'))->apply([$parent]);

        static::assertCount(2, $parent->getSlots()['content']->getElements());
    }
}
