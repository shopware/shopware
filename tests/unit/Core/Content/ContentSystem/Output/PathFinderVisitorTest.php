<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Output\PathFinderVisitor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PathFinderVisitor::class)]
class PathFinderVisitorTest extends TestCase
{
    #[TestDox('finds element and returns correct path from root')]
    public function testFindElementReturnsCorrectPath(): void
    {
        $rootId = 'root-element-id';

        $rootOnly = ContentElementBuilder::create('block', $rootId)->build();
        $rootVisitor = new PathFinderVisitor($rootId);
        $rootOnly->traverse($rootVisitor);

        static::assertSame([$rootId], $rootVisitor->getPath());

        $parentId = 'parent-element-id';
        $targetId = 'target-element-id';

        $target = ContentElementBuilder::create('text', $targetId)->build();
        $parent = ContentElementBuilder::create('row', $parentId)
            ->withSlot('default', [$target])
            ->build();
        $root = ContentElementBuilder::create('block', $rootId)
            ->withSlot('default', [$parent])
            ->build();

        $nestedVisitor = new PathFinderVisitor($targetId);
        $root->traverse($nestedVisitor);

        static::assertSame([$rootId, $parentId, $targetId], $nestedVisitor->getPath());
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
