<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RemoveElement::class)]
class RemoveElementTest extends TestCase
{
    #[TestDox('deletes the element together with its whole subtree and reports no affected survivor')]
    public function testRemoveDeletesElementAndSubtree(): void
    {
        $tree = new StoredTree([
            new StoredElement('keep', 'Sw:Block'),
            new StoredElement('drop', 'Sw:Block', [], [], [
                'content' => [new StoredElement('child', 'Sw:Block')],
            ]),
        ]);

        $remove = new RemoveElement('drop');
        $result = $remove->apply($tree);

        static::assertCount(1, $result->roots);
        static::assertSame('keep', $result->roots[0]->id);
        static::assertSame([], $remove->affected());
    }

    #[TestDox('removes a nested element while keeping its siblings')]
    public function testRemoveNestedElementKeepsSiblings(): void
    {
        $parent = new StoredElement('parent', 'Sw:Block', [], [], [
            'content' => [
                new StoredElement('a', 'Sw:Block'),
                new StoredElement('b', 'Sw:Block'),
            ],
        ]);

        $result = (new RemoveElement('a'))->apply(new StoredTree([$parent]));

        static::assertSame(['b'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots[0]->slots['content']));
    }

    #[TestDox('leaves a surviving element data requirements untouched')]
    public function testRemoveLeavesSurvivorWiringUntouched(): void
    {
        $requirement = new DataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $survivor = new StoredElement('survivor', 'Sw:Block', ['product' => $requirement]);
        $tree = new StoredTree([$survivor, new StoredElement('drop', 'Sw:Block')]);

        $result = (new RemoveElement('drop'))->apply($tree);

        static::assertSame(['product' => $requirement], $result->roots[0]->dataRequirements);
    }

    #[TestDox('rejects removing an element absent from the tree with a 400')]
    public function testRemoveMissingElementRejected(): void
    {
        $remove = new RemoveElement('ghost');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $remove->apply(new StoredTree([new StoredElement('other', 'Sw:Block')]));
    }
}
