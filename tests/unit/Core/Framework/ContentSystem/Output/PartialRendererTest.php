<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\ContentSystem\Output\ElementTreePruner;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\Output\SubTreeExtractor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\RenderedElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PartialRenderer::class)]
class PartialRendererTest extends TestCase
{
    private PartialRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new PartialRenderer(
            new ElementTreePruner(),
            new ContextDependencyAnalyzer(),
            new SubTreeExtractor(),
        );
    }

    #[TestDox('prunes to the target element, skipping the roots that do not contain it')]
    public function testPruneToTargetSkipsRootsWithoutTheTarget(): void
    {
        $root1 = StoredElementBuilder::create('section', 'r1')
            ->withSlot('default', [StoredElementBuilder::create('text', 'bystander')->build()])
            ->build();

        $target = StoredElementBuilder::create('text', 'target')->build();
        $root2 = StoredElementBuilder::create('section', 'r2')
            ->withSlot('default', [$target])
            ->build();

        $result = $this->renderer->pruneToTarget([$root1, $root2], 'target');

        static::assertCount(1, $result);
        static::assertSame('target', $result[0]->id);
    }

    #[TestDox('extracts target from first element containing it')]
    public function testExtractTargetReturnsFirstMatch(): void
    {
        $target = RenderedElementBuilder::create('text', 'target')->build();
        $root1 = RenderedElementBuilder::create('section', 'r1')
            ->withSlot('default', [$target])
            ->build();
        $root2 = RenderedElementBuilder::create('section', 'r2')->build();

        $result = $this->renderer->extractTarget([$root1, $root2], 'target');

        static::assertSame('target', $result->id);
    }

    #[TestDox('returns empty array when target not found in any root during pruning')]
    public function testPruneToTargetReturnsEmptyWhenNotFoundInAnyRoot(): void
    {
        $root = StoredElementBuilder::create('section', 'r1')->build();

        $result = $this->renderer->pruneToTarget([$root], 'nonexistent');

        static::assertSame([], $result);
    }

    #[TestDox('throws when target element not found in any root during extraction')]
    public function testExtractTargetThrowsWhenNotFound(): void
    {
        $root = RenderedElementBuilder::create('section', 'r1')->build();

        $this->expectExceptionObject(ContentSystemException::elementNotFound('missing-id'));

        $this->renderer->extractTarget([$root], 'missing-id');
    }
}
