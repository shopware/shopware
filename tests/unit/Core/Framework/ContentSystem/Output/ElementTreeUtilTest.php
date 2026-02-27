<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Output\ElementTreeUtil;
use Shopware\Tests\Unit\Core\Framework\ContentSystem\_helper\ContentElementBuilder;

/**
 * @internal
 */
#[CoversClass(ElementTreeUtil::class)]
class ElementTreeUtilTest extends TestCase
{
    private ElementTreeUtil $util;

    private ContextDependencyAnalyzer $dependencyAnalyzer;

    protected function setUp(): void
    {
        $this->util = new ElementTreeUtil();
        $this->dependencyAnalyzer = new ContextDependencyAnalyzer();
    }

    #[TestDox('finds path from root to target element including both endpoints')]
    public function testFindPathToElementReturnsPathFromRootToTarget(): void
    {
        $childId = 'child-id';
        $grandChildId = 'grandchild-id';
        $rootId = 'root-id';

        $grandChild = ContentElementBuilder::create('leaf-component', $grandChildId)->build();
        $child = ContentElementBuilder::create('middle-component', $childId)
            ->withSlot('default', [$grandChild])
            ->build();
        $root = ContentElementBuilder::create('root-component', $rootId)
            ->withSlot('default', [$child])
            ->build();

        $path = $this->util->findPathToElement($root, $grandChildId);

        static::assertSame([$rootId, $childId, $grandChildId], $path);
    }

    #[TestDox('returns empty array when target element is not in tree')]
    public function testFindPathToElementReturnsEmptyArrayWhenNotFound(): void
    {
        $root = ContentElementBuilder::create('root-component', 'root-id')
            ->withSlot('default', [
                ContentElementBuilder::create('child-component', 'child-id')->build(),
            ])
            ->build();

        $path = $this->util->findPathToElement($root, 'non-existent-id');

        static::assertSame([], $path);
    }

    #[TestDox('returns a cloned target element when target has no context consumers')]
    public function testPruneReturnsClonedTargetWhenTargetHasNoContextConsumers(): void
    {
        $targetId = 'target-id';
        $rootId = 'root-id';
        $childId = 'child-id';

        $target = ContentElementBuilder::create('target-component', $targetId)->build();
        $sibling = ContentElementBuilder::create('sibling-component', 'sibling-id')->build();
        $child = ContentElementBuilder::create('child-component', $childId)
            ->withSlot('default', [$target, $sibling])
            ->build();
        $root = ContentElementBuilder::create('root-component', $rootId)
            ->withSlot('default', [$child])
            ->build();

        $pruned = $this->util->pruneToPathAndDescendants($root, $targetId, $this->dependencyAnalyzer);

        // Since target has no context consumers, it is the data root — pruned tree starts at target
        static::assertSame($targetId, $pruned->getId());
        // No children in pruned tree — target is a leaf
        static::assertFalse($pruned->hasSlots());
    }

    #[TestDox('selects context-providing ancestor as data root and removes siblings from its slots')]
    public function testPruneSelectsDataRootAndPrunesSiblings(): void
    {
        $targetId = 'target-id';
        $providerId = 'provider-id';
        $rootId = 'root-id';

        $target = ContentElementBuilder::create('target-component', $targetId)
            ->withConsumer('product', ContextType::Single)
            ->build();
        $sibling = ContentElementBuilder::create('sibling-component', 'sibling-id')->build();

        $provider = ContentElementBuilder::create('provider-component', $providerId)
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$target, $sibling])
            ->build();

        $root = ContentElementBuilder::create('root-component', $rootId)
            ->withSlot('default', [$provider])
            ->build();

        $pruned = $this->util->pruneToPathAndDescendants($root, $targetId, $this->dependencyAnalyzer);

        // Provider is the data root (doesn't consume), so pruned tree starts there
        static::assertSame($providerId, $pruned->getId());

        // Pruned provider should contain only the target in its slot, not the sibling
        $slots = $pruned->getSlots();
        static::assertArrayHasKey('default', $slots);

        $children = $slots['default']->getElements();
        static::assertCount(1, $children);
        static::assertSame($targetId, $children[0]->getId());
    }

    #[TestDox('preserves correct slot name when target is in non-default slot')]
    public function testPrunePreservesSlotName(): void
    {
        $targetId = 'target-id';
        $providerId = 'provider-id';

        $headerChild = ContentElementBuilder::create('header-component', 'header-child')->build();
        $target = ContentElementBuilder::create('target-component', $targetId)
            ->withConsumer('product', ContextType::Single)
            ->build();

        $provider = ContentElementBuilder::create('provider-component', $providerId)
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('header', [$headerChild])
            ->withSlot('content', [$target])
            ->build();

        $root = ContentElementBuilder::create('root-component', 'root-id')
            ->withSlot('default', [$provider])
            ->build();

        $pruned = $this->util->pruneToPathAndDescendants($root, $targetId, $this->dependencyAnalyzer);

        static::assertSame($providerId, $pruned->getId());

        $slots = $pruned->getSlots();
        static::assertCount(1, $slots);
        static::assertArrayHasKey('content', $slots);
        static::assertArrayNotHasKey('header', $slots);

        $children = $slots['content']->getElements();
        static::assertCount(1, $children);
        static::assertSame($targetId, $children[0]->getId());
    }

    #[TestDox('reconstructs multi-level pruned tree when both target and parent consume context')]
    public function testPruneMultiLevel(): void
    {
        $targetId = 'target-id';
        $parentId = 'parent-id';
        $grandparentId = 'grandparent-id';

        $target = ContentElementBuilder::create('target-component', $targetId)
            ->withConsumer('listing', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent-component', $parentId)
            ->withConsumer('product', ContextType::Single)
            ->withSlot('default', [$target])
            ->build();

        $grandparent = ContentElementBuilder::create('grandparent-component', $grandparentId)
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$parent])
            ->build();

        $root = ContentElementBuilder::create('root-component', 'root-id')
            ->withSlot('default', [$grandparent])
            ->build();

        $pruned = $this->util->pruneToPathAndDescendants($root, $targetId, $this->dependencyAnalyzer);

        // Grandparent is data root (provides context but doesn't consume)
        static::assertSame($grandparentId, $pruned->getId());

        // Grandparent → parent → target chain preserved
        $parentSlots = $pruned->getSlots();
        static::assertCount(1, $parentSlots);
        $prunedParent = $parentSlots['default']->getElements()[0];
        static::assertSame($parentId, $prunedParent->getId());

        $targetSlots = $prunedParent->getSlots();
        static::assertCount(1, $targetSlots);
        $prunedTarget = $targetSlots['default']->getElements()[0];
        static::assertSame($targetId, $prunedTarget->getId());
    }

    #[TestDox('preserves descendant elements below the target when target has children')]
    public function testPrunePreservesDescendantsBelowTarget(): void
    {
        $targetId = 'target-id';
        $childId = 'child-id';
        $grandChildId = 'grandchild-id';

        $grandChild = ContentElementBuilder::create('leaf-component', $grandChildId)->build();

        $child = ContentElementBuilder::create('inner-component', $childId)
            ->withSlot('default', [$grandChild])
            ->build();

        $target = ContentElementBuilder::create('target-component', $targetId)
            ->withConsumer('product', ContextType::Single)
            ->withSlot('main', [$child])
            ->build();

        $provider = ContentElementBuilder::create('provider-component', 'provider-id')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$target])
            ->build();

        $root = ContentElementBuilder::create('root-component', 'root-id')
            ->withSlot('default', [$provider])
            ->build();

        $pruned = $this->util->pruneToPathAndDescendants($root, $targetId, $this->dependencyAnalyzer);

        // Provider is data root — pruned tree starts there
        static::assertSame('provider-id', $pruned->getId());

        // Provider → target
        $providerSlots = $pruned->getSlots();
        static::assertArrayHasKey('default', $providerSlots);
        $prunedTarget = $providerSlots['default']->getElements()[0];
        static::assertSame($targetId, $prunedTarget->getId());

        // Target still has its children (descendants preserved)
        static::assertTrue($prunedTarget->hasSlots());
        $targetSlots = $prunedTarget->getSlots();
        static::assertArrayHasKey('main', $targetSlots);
        static::assertSame($childId, $targetSlots['main']->getElements()[0]->getId());
    }

    #[TestDox('throws element-not-found exception when target element is not in tree')]
    public function testPruneToPathAndDescendantsThrowsWhenElementNotFound(): void
    {
        $root = ContentElementBuilder::create('root-component', 'root-id')->build();

        $this->expectExceptionObject(ContentSystemException::elementNotFound('non-existent-id'));

        $this->util->pruneToPathAndDescendants($root, 'non-existent-id', $this->dependencyAnalyzer);
    }
}
