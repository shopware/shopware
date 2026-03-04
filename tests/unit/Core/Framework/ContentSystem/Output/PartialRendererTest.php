<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\ContentSystem\Output\ElementTreeUtil;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\Output\SubTreeExtractor;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;

/**
 * @internal
 */
#[CoversClass(PartialRenderer::class)]
class PartialRendererTest extends TestCase
{
    private PartialRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new PartialRenderer(
            new ElementTreeUtil(),
            new ContextDependencyAnalyzer(),
            new SubTreeExtractor(),
        );
    }

    #[TestDox('prunes to target element, catching exceptions for roots where target is not found')]
    public function testPruneToTargetCatchesExceptionsAndContinues(): void
    {
        $root1 = ContentElementBuilder::create('section', 'r1')->build();

        $target = ContentElementBuilder::create('text', 'target')->build();
        $root2 = ContentElementBuilder::create('section', 'r2')
            ->withSlot('default', [$target])
            ->build();

        $result = $this->renderer->pruneToTarget([$root1, $root2], 'target');

        static::assertCount(1, $result);
        static::assertSame('target', $result[0]->getId());
    }

    #[TestDox('returns empty array when target not found in any root during pruning')]
    public function testPruneToTargetReturnsEmptyWhenNotFoundInAnyRoot(): void
    {
        $root = ContentElementBuilder::create('section', 'r1')->build();

        $result = $this->renderer->pruneToTarget([$root], 'nonexistent');

        static::assertSame([], $result);
    }

    #[TestDox('extracts target from first element containing it')]
    public function testExtractTargetReturnsFirstMatch(): void
    {
        $target = ContentElementBuilder::create('text', 'target')->build();
        $root1 = ContentElementBuilder::create('section', 'r1')
            ->withSlot('default', [$target])
            ->build();
        $root2 = ContentElementBuilder::create('section', 'r2')->build();

        $result = $this->renderer->extractTarget([$root1, $root2], 'target');

        static::assertSame('target', $result->getId());
    }

    #[TestDox('throws when target element not found in any root during extraction')]
    public function testExtractTargetThrowsWhenNotFound(): void
    {
        $root = ContentElementBuilder::create('section', 'r1')->build();

        $this->expectExceptionObject(ContentSystemException::elementNotFound('missing-id'));

        $this->renderer->extractTarget([$root], 'missing-id');
    }
}
