<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteContext;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutWriteContext::class)]
class LayoutWriteContextTest extends TestCase
{
    #[TestDox('hands back the tree remembered for the same encoded value')]
    public function testTreeForReturnsTheTreeRememberedForTheSameBytes(): void
    {
        $tree = $this->tree('el-1');
        $write = $this->write();
        $memo = new LayoutWriteContext();

        $memo->remember($write, '[{"id":"el-1"}]', $tree);

        static::assertSame($tree, $memo->treeFor($write, '[{"id":"el-1"}]'));
    }

    #[TestDox('keeps the trees of different encoded values apart')]
    public function testTreeForKeepsDifferentBytesApart(): void
    {
        $first = $this->tree('el-1');
        $second = $this->tree('el-2');
        $write = $this->write();
        $memo = new LayoutWriteContext();

        $memo->remember($write, '[{"id":"el-1"}]', $first);
        $memo->remember($write, '[{"id":"el-2"}]', $second);

        static::assertSame([$first, $second], [$memo->treeFor($write, '[{"id":"el-1"}]'), $memo->treeFor($write, '[{"id":"el-2"}]')]);
    }

    #[TestDox('reports an encoded value no row yielded as null')]
    public function testTreeForUnknownBytesReturnsNull(): void
    {
        $write = $this->write();
        $memo = new LayoutWriteContext();
        $memo->remember($write, '[{"id":"el-1"}]', $this->tree('el-1'));

        static::assertNull($memo->treeFor($write, '[{"id":"el-2"}]'));
    }

    /**
     * A retried write dispatches the validation event again without encoding again, so a lookup must not take
     * the entry out.
     */
    #[TestDox('hands back the same tree on a second lookup of one encoded value')]
    public function testTreeForLeavesTheEntryInPlace(): void
    {
        $tree = $this->tree('el-1');
        $write = $this->write();
        $memo = new LayoutWriteContext();
        $memo->remember($write, '[{"id":"el-1"}]', $tree);

        $first = $memo->treeFor($write, '[{"id":"el-1"}]');

        static::assertSame([$tree, $tree], [$first, $memo->treeFor($write, '[{"id":"el-1"}]')]);
    }

    /**
     * Two writes on one `Context` that encode the same bytes must each find their own tree, and a write that
     * encoded nothing must find none, although the other write remembered that value.
     */
    #[TestDox('keeps the entries of two writes on one context apart, also under the same encoded value')]
    public function testTwoWritesOnOneContextKeepSeparateEntries(): void
    {
        $context = Context::createDefaultContext();
        $outer = WriteContext::createFromContext($context);
        $nested = WriteContext::createFromContext($context);
        $bystander = WriteContext::createFromContext($context);
        $outerTree = $this->tree('el-outer');
        $nestedTree = $this->tree('el-nested');
        $memo = new LayoutWriteContext();

        $memo->remember($outer, '[{"id":"el-1"}]', $outerTree);
        $memo->remember($nested, '[{"id":"el-1"}]', $nestedTree);

        static::assertSame(
            [$outerTree, $nestedTree, null],
            [$memo->treeFor($outer, '[{"id":"el-1"}]'), $memo->treeFor($nested, '[{"id":"el-1"}]'), $memo->treeFor($bystander, '[{"id":"el-1"}]')]
        );
    }

    /**
     * The entries are held weakly by their `WriteContext`: once nothing references the write any more, its trees
     * are released, so a `Context` reused across many writes does not accumulate them.
     */
    #[TestDox('releases the entries of a write once its write context is gone')]
    public function testEntriesAreReleasedWithTheirWriteContext(): void
    {
        $memo = new LayoutWriteContext();
        $write = $this->write();
        $tree = $this->tree('el-1');
        $memo->remember($write, '[{"id":"el-1"}]', $tree);
        $remembered = \WeakReference::create($tree);
        unset($tree);

        unset($write);
        gc_collect_cycles();

        static::assertNull($remembered->get());
    }

    private function write(): WriteContext
    {
        return WriteContext::createFromContext(Context::createDefaultContext());
    }

    private function tree(string $elementId): StoredTree
    {
        return new StoredTree([new StoredElement($elementId, 'Sw:Block')]);
    }
}
