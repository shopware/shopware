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
        $memo = $this->memo();

        $memo->remember('[{"id":"el-1"}]', $tree);

        static::assertSame($tree, $memo->treeFor('[{"id":"el-1"}]'));
    }

    #[TestDox('keeps the trees of different encoded values apart')]
    public function testTreeForKeepsDifferentBytesApart(): void
    {
        $first = $this->tree('el-1');
        $second = $this->tree('el-2');
        $memo = $this->memo();

        $memo->remember('[{"id":"el-1"}]', $first);
        $memo->remember('[{"id":"el-2"}]', $second);

        static::assertSame([$first, $second], [$memo->treeFor('[{"id":"el-1"}]'), $memo->treeFor('[{"id":"el-2"}]')]);
    }

    #[TestDox('reports an encoded value no row yielded as null')]
    public function testTreeForUnknownBytesReturnsNull(): void
    {
        $memo = $this->memo();
        $memo->remember('[{"id":"el-1"}]', $this->tree('el-1'));

        static::assertNull($memo->treeFor('[{"id":"el-2"}]'));
    }

    /**
     * A retried write dispatches the validation event again without encoding again, so a lookup must not take
     * the entry out.
     */
    #[TestDox('hands back the same tree on a second lookup of one encoded value')]
    public function testTreeForLeavesTheEntryInPlace(): void
    {
        $tree = $this->tree('el-1');
        $memo = $this->memo();
        $memo->remember('[{"id":"el-1"}]', $tree);

        $first = $memo->treeFor('[{"id":"el-1"}]');

        static::assertSame([$tree, $tree], [$first, $memo->treeFor('[{"id":"el-1"}]')]);
    }

    #[TestDox('belongs to the write that opened it and to no other')]
    public function testOwnershipIsPerWrite(): void
    {
        $context = Context::createDefaultContext();
        $write = WriteContext::createFromContext($context);
        $laterWrite = WriteContext::createFromContext($context);

        $memo = new LayoutWriteContext($write);

        static::assertTrue($memo->ownedBy($write));
        static::assertFalse($memo->ownedBy($laterWrite));
    }

    private function memo(): LayoutWriteContext
    {
        return new LayoutWriteContext(WriteContext::createFromContext(Context::createDefaultContext()));
    }

    private function tree(string $elementId): StoredTree
    {
        return new StoredTree([new StoredElement($elementId, 'Sw:Block')]);
    }
}
