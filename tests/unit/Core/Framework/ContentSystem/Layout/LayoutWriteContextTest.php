<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteContext;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutWriteContext::class)]
class LayoutWriteContextTest extends TestCase
{
    #[TestDox('hands back the tree remembered for an entity and primary key')]
    public function testConsumeReturnsTheRememberedTree(): void
    {
        $tree = $this->tree('el-1');
        $memo = new LayoutWriteContext();

        $memo->remember('content_layout', 'layout-1', $tree);

        static::assertSame($tree, $memo->consume('content_layout', 'layout-1'));
    }

    #[TestDox('removes the entry as it hands it out, so a second read finds nothing')]
    public function testConsumeRemovesTheEntry(): void
    {
        $memo = new LayoutWriteContext();
        $memo->remember('content_layout', 'layout-1', $this->tree('el-1'));

        $memo->consume('content_layout', 'layout-1');

        static::assertNull($memo->consume('content_layout', 'layout-1'));
    }

    #[TestDox('is empty once the only entry has been consumed')]
    public function testMemoIsEmptyAfterItsOnlyEntryIsConsumed(): void
    {
        $memo = new LayoutWriteContext();
        $memo->remember('content_layout', 'layout-1', $this->tree('el-1'));

        $memo->consume('content_layout', 'layout-1');

        static::assertTrue($memo->isEmpty());
    }

    #[TestDox('reports an absent entry as null rather than as an error')]
    public function testConsumeOfAnAbsentEntryReturnsNull(): void
    {
        $memo = new LayoutWriteContext();

        static::assertNull($memo->consume('content_layout', 'never-written'));
    }

    /**
     * One of the memo-lifetime cases: the serializer memoizes per row and the DAL keeps a command per row, so
     * a batch that writes the same row twice must find both of its trees here, oldest first.
     */
    #[TestDox('hands back both trees in order when the same entity and primary key are remembered twice')]
    public function testRememberingTheSameKeyTwiceHandsBothBackInOrder(): void
    {
        $first = $this->tree('el-1');
        $second = $this->tree('el-2');
        $memo = new LayoutWriteContext();

        $memo->remember('content_layout', 'layout-1', $first);
        $memo->remember('content_layout', 'layout-1', $second);

        static::assertSame([$first, $second], [
            $memo->consume('content_layout', 'layout-1'),
            $memo->consume('content_layout', 'layout-1'),
        ]);
    }

    #[TestDox('stays non-empty until both trees remembered under one key are consumed')]
    public function testKeyRememberedTwiceIsEmptyOnlyAfterBothTreesAreConsumed(): void
    {
        $memo = new LayoutWriteContext();
        $memo->remember('content_layout', 'layout-1', $this->tree('el-1'));
        $memo->remember('content_layout', 'layout-1', $this->tree('el-2'));

        $memo->consume('content_layout', 'layout-1');

        static::assertFalse($memo->isEmpty());

        $memo->consume('content_layout', 'layout-1');

        static::assertTrue($memo->isEmpty());
    }

    #[TestDox('reports null on a third read of a key that was remembered twice')]
    public function testThirdConsumeOfAKeyRememberedTwiceReturnsNull(): void
    {
        $memo = new LayoutWriteContext();
        $memo->remember('content_layout', 'layout-1', $this->tree('el-1'));
        $memo->remember('content_layout', 'layout-1', $this->tree('el-2'));

        $memo->consume('content_layout', 'layout-1');
        $memo->consume('content_layout', 'layout-1');

        static::assertNull($memo->consume('content_layout', 'layout-1'));
    }

    #[TestDox('matches an upper-case primary key against the lower-case one the command decodes')]
    public function testPrimaryKeyMatchingIgnoresHexCasing(): void
    {
        $tree = $this->tree('el-1');
        $memo = new LayoutWriteContext();

        $memo->remember('content_layout', 'AABBCCDD', $tree);

        static::assertSame($tree, $memo->consume('content_layout', 'aabbccdd'));
    }

    #[TestDox('keeps entries of different entities apart under the same primary key')]
    public function testEntriesOfDifferentEntitiesDoNotCollide(): void
    {
        $layoutTree = $this->tree('el-1');
        $memo = new LayoutWriteContext();

        $memo->remember('content_layout', 'shared-id', $layoutTree);
        $memo->remember('other_entity', 'shared-id', $this->tree('el-2'));

        static::assertSame($layoutTree, $memo->consume('content_layout', 'shared-id'));
        static::assertFalse($memo->isEmpty());
    }

    private function tree(string $elementId): StoredTree
    {
        return new StoredTree([new StoredElement($elementId, 'Sw:Block')]);
    }
}
