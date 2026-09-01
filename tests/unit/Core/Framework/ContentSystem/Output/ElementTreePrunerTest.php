<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Output\ElementTreePruner;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElementTreePruner::class)]
class ElementTreePrunerTest extends TestCase
{
    private ElementTreePruner $pruner;

    private ContextDependencyAnalyzer $dependencyAnalyzer;

    protected function setUp(): void
    {
        $this->pruner = new ElementTreePruner();
        $this->dependencyAnalyzer = new ContextDependencyAnalyzer();
    }

    #[TestDox('returns the target element itself when target has no context consumers')]
    public function testPruneReturnsTargetWhenTargetHasNoContextConsumers(): void
    {
        $targetId = 'target-id';
        $childId = 'child-id';

        $target = StoredElementBuilder::create('target-component', $targetId)->build();
        $sibling = StoredElementBuilder::create('sibling-component', 'sibling-id')->build();
        $child = StoredElementBuilder::create('child-component', $childId)
            ->withSlot('default', [$target, $sibling])
            ->build();
        $root = StoredElementBuilder::create('root-component', 'root-id')
            ->withSlot('default', [$child])
            ->build();

        $pruned = $this->pruner->pruneToPathAndDescendants($root, $targetId, $this->dependencyAnalyzer);

        static::assertNotNull($pruned);
        // Since target has no context consumers, it is the data root — pruned tree starts at target
        static::assertSame($targetId, $pruned->id);
        // No children in pruned tree — target is a leaf
        static::assertSame([], $pruned->slots);
    }

    #[TestDox('selects context-providing ancestor as data root and removes siblings from its slots')]
    public function testPruneSelectsDataRootAndPrunesSiblings(): void
    {
        $targetId = 'target-id';
        $providerId = 'provider-id';

        $target = StoredElementBuilder::create('target-component', $targetId)
            ->withConsumer('product', ContextType::Single)
            ->build();
        $sibling = StoredElementBuilder::create('sibling-component', 'sibling-id')->build();

        $provider = StoredElementBuilder::create('provider-component', $providerId)
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$target, $sibling])
            ->build();

        $root = StoredElementBuilder::create('root-component', 'root-id')
            ->withSlot('default', [$provider])
            ->build();

        $pruned = $this->pruner->pruneToPathAndDescendants($root, $targetId, $this->dependencyAnalyzer);

        static::assertNotNull($pruned);
        // Provider is the data root (doesn't consume), so pruned tree starts there
        static::assertSame($providerId, $pruned->id);

        // Pruned provider should contain only the target in its slot, not the sibling
        static::assertArrayHasKey('default', $pruned->slots);

        $children = $pruned->slots['default'];
        static::assertCount(1, $children);
        static::assertSame($targetId, $children[0]->id);
    }

    #[TestDox('preserves correct slot name when target is in non-default slot')]
    public function testPrunePreservesSlotName(): void
    {
        $targetId = 'target-id';
        $providerId = 'provider-id';

        $headerChild = StoredElementBuilder::create('header-component', 'header-child')->build();
        $target = StoredElementBuilder::create('target-component', $targetId)
            ->withConsumer('product', ContextType::Single)
            ->build();

        $provider = StoredElementBuilder::create('provider-component', $providerId)
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('header', [$headerChild])
            ->withSlot('content', [$target])
            ->build();

        $root = StoredElementBuilder::create('root-component', 'root-id')
            ->withSlot('default', [$provider])
            ->build();

        $pruned = $this->pruner->pruneToPathAndDescendants($root, $targetId, $this->dependencyAnalyzer);

        static::assertNotNull($pruned);
        static::assertSame($providerId, $pruned->id);

        static::assertCount(1, $pruned->slots);
        static::assertArrayHasKey('content', $pruned->slots);
        static::assertArrayNotHasKey('header', $pruned->slots);

        $children = $pruned->slots['content'];
        static::assertCount(1, $children);
        static::assertSame($targetId, $children[0]->id);
    }

    #[TestDox('reconstructs multi-level pruned tree and preserves style on each reconstructed ancestor and the target')]
    public function testPruneMultiLevel(): void
    {
        $targetId = 'target-id';
        $parentId = 'parent-id';
        $grandparentId = 'grandparent-id';

        $grandparentStyle = new ElementStyle(['col-span' => ['md' => 6]]);
        $targetStyle = new ElementStyle(['display' => ['xs' => 'none']]);

        $target = StoredElementBuilder::create('target-component', $targetId)
            ->withConsumer('listing', ContextType::Single)
            ->withStyle($targetStyle)
            ->build();

        $parent = StoredElementBuilder::create('parent-component', $parentId)
            ->withConsumer('product', ContextType::Single)
            ->withSlot('default', [$target])
            ->build();

        $grandparent = StoredElementBuilder::create('grandparent-component', $grandparentId)
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$parent])
            ->withStyle($grandparentStyle)
            ->build();

        $root = StoredElementBuilder::create('root-component', 'root-id')
            ->withSlot('default', [$grandparent])
            ->build();

        $pruned = $this->pruner->pruneToPathAndDescendants($root, $targetId, $this->dependencyAnalyzer);

        static::assertNotNull($pruned);
        // Grandparent is data root (provides context but doesn't consume); it is rebuilt through
        // withSlots(), which carries every field it does not override, style included.
        static::assertSame($grandparentId, $pruned->id);
        static::assertSame(['col-span' => ['md' => 6]], $pruned->style->toArray());

        // Grandparent → parent → target chain preserved
        static::assertCount(1, $pruned->slots);
        $prunedParent = $pruned->slots['default'][0];
        static::assertSame($parentId, $prunedParent->id);

        static::assertCount(1, $prunedParent->slots);
        $prunedTarget = $prunedParent->slots['default'][0];
        static::assertSame($targetId, $prunedTarget->id);
        static::assertSame(['display' => ['xs' => 'none']], $prunedTarget->style->toArray());
    }

    #[TestDox('carries the attribution of a reconstructed ancestor across the rebuild')]
    public function testPruneCarriesAncestorAttribution(): void
    {
        $target = StoredElementBuilder::create('target-component', 'target-id')
            ->withConsumer('product', ContextType::Single)
            ->build();

        $provider = StoredElementBuilder::create('provider-component', 'provider-id')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withAttributedSpecification('product', 'product:default')
            ->withSlot('default', [$target])
            ->build();

        $root = StoredElementBuilder::create('root-component', 'root-id')
            ->withSlot('default', [$provider])
            ->build();

        $pruned = $this->pruner->pruneToPathAndDescendants($root, 'target-id', $this->dependencyAnalyzer);

        static::assertNotNull($pruned);
        static::assertSame(['product' => 'product:default'], $pruned->attributedSpecifications);
    }

    #[TestDox('preserves descendant elements below the target when target has children')]
    public function testPrunePreservesDescendantsBelowTarget(): void
    {
        $targetId = 'target-id';
        $childId = 'child-id';
        $grandChildId = 'grandchild-id';

        $grandChild = StoredElementBuilder::create('leaf-component', $grandChildId)->build();

        $child = StoredElementBuilder::create('inner-component', $childId)
            ->withSlot('default', [$grandChild])
            ->build();

        $target = StoredElementBuilder::create('target-component', $targetId)
            ->withConsumer('product', ContextType::Single)
            ->withSlot('main', [$child])
            ->build();

        $provider = StoredElementBuilder::create('provider-component', 'provider-id')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$target])
            ->build();

        $root = StoredElementBuilder::create('root-component', 'root-id')
            ->withSlot('default', [$provider])
            ->build();

        $pruned = $this->pruner->pruneToPathAndDescendants($root, $targetId, $this->dependencyAnalyzer);

        static::assertNotNull($pruned);
        // Provider is data root — pruned tree starts there
        static::assertSame('provider-id', $pruned->id);

        // Provider → target
        static::assertArrayHasKey('default', $pruned->slots);
        $prunedTarget = $pruned->slots['default'][0];
        static::assertSame($targetId, $prunedTarget->id);

        // Target still has its children (descendants preserved)
        static::assertArrayHasKey('main', $prunedTarget->slots);
        static::assertSame($childId, $prunedTarget->slots['main'][0]->id);
    }

    #[TestDox('walks through the duplicate-id ancestor that actually holds the target, not its namesake in an earlier slot')]
    public function testPruneWalksThroughTheAncestorHoldingTheTarget(): void
    {
        $target = StoredElementBuilder::create('target-component', 'target-id')->build();

        $decoyMiddle = StoredElementBuilder::create('middle-component', 'middle-id')
            ->withSlot('default', [StoredElementBuilder::create('leaf-component', 'decoy-leaf')->build()])
            ->build();
        $realMiddle = StoredElementBuilder::create('middle-component', 'middle-id')
            ->withSlot('default', [$target])
            ->build();

        $root = StoredElementBuilder::create('root-component', 'root-id')
            ->withSlot('first', [$decoyMiddle])
            ->withSlot('second', [$realMiddle])
            ->build();

        // Fixture guard: the two ancestors really do share an id, and the earlier slot is really the
        // one that does not hold the target — the pair a search by id alone cannot tell apart.
        static::assertSame($decoyMiddle->id, $realMiddle->id);
        static::assertSame(['first', 'second'], array_keys($root->slots));
        static::assertNull($this->pruner->pruneToPathAndDescendants($decoyMiddle, 'target-id', $this->dependencyAnalyzer));

        $pruned = $this->pruner->pruneToPathAndDescendants($root, 'target-id', $this->dependencyAnalyzer);

        static::assertNotNull($pruned);
        // The target consumes nothing, so it is its own data root and the prune stops there.
        static::assertSame('target-id', $pruned->id);
    }

    #[TestDox('reports absence with null when the target element is not in the tree')]
    public function testPruneToPathAndDescendantsReturnsNullWhenElementNotFound(): void
    {
        $root = StoredElementBuilder::create('root-component', 'root-id')->build();

        static::assertNull(
            $this->pruner->pruneToPathAndDescendants($root, 'non-existent-id', $this->dependencyAnalyzer)
        );
    }

    #[TestDox('leaves the input tree untouched while rebuilding the pruned path')]
    public function testPruneLeavesTheInputTreeUntouched(): void
    {
        $target = StoredElementBuilder::create('target-component', 'target-id')
            ->withConsumer('product', ContextType::Single)
            ->build();
        $sibling = StoredElementBuilder::create('sibling-component', 'sibling-id')->build();
        $provider = StoredElementBuilder::create('provider-component', 'provider-id')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$target, $sibling])
            ->build();

        $this->pruner->pruneToPathAndDescendants($provider, 'target-id', $this->dependencyAnalyzer);

        static::assertCount(2, $provider->slots['default']);
    }
}
