<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Output\PathFinderVisitor;
use Shopware\Tests\Unit\Core\Framework\ContentSystem\_helper\ContentElementBuilder;

/**
 * @internal
 */
#[CoversClass(PathFinderVisitor::class)]
class PathFinderVisitorTest extends TestCase
{
    #[TestDox('returns single-element path when target is the root element')]
    public function testReturnsRootPathWhenTargetIsRoot(): void
    {
        $rootId = 'root-element-id';

        $root = ContentElementBuilder::create('block', $rootId)->build();
        $visitor = new PathFinderVisitor($rootId);
        $root->traverse($visitor);

        static::assertSame([$rootId], $visitor->getPath());
    }

    #[TestDox('finds element and returns correct path from root')]
    public function testFindElementReturnsCorrectPath(): void
    {
        $rootId = 'root-element-id';
        $parentId = 'parent-element-id';
        $targetId = 'target-element-id';

        $target = ContentElementBuilder::create('text', $targetId)->build();
        $parent = ContentElementBuilder::create('row', $parentId)
            ->withSlot('default', [$target])
            ->build();
        $root = ContentElementBuilder::create('block', $rootId)
            ->withSlot('default', [$parent])
            ->build();

        $visitor = new PathFinderVisitor($targetId);
        $root->traverse($visitor);

        static::assertSame([$rootId, $parentId, $targetId], $visitor->getPath());
    }

    #[TestDox('returns empty path when element not found')]
    public function testReturnsEmptyPathWhenElementNotFound(): void
    {
        $rootId = 'root-element-id';
        $childId = 'child-element-id';
        $missingId = 'non-existent-element-id';

        $child = ContentElementBuilder::create('text', $childId)->build();
        $root = ContentElementBuilder::create('block', $rootId)
            ->withSlot('default', [$child])
            ->build();

        $visitor = new PathFinderVisitor($missingId);
        $root->traverse($visitor);

        static::assertSame([], $visitor->getPath());
    }
}
