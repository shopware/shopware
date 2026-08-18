<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\RenderScaffolding;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RenderScaffolding::class)]
class RenderScaffoldingTest extends TestCase
{
    /**
     * Whether the two facts are derived correctly, and whether the finishing steps act on them, is
     * `ContentPipeline`'s behaviour and is pinned by `ContentPipelineTest`. This covers the record
     * itself: every combination it can hold, it hands back unchanged.
     */
    #[DataProvider('scaffoldingProvider')]
    #[TestDox('exposes each combination of the two facts exactly as constructed')]
    public function testExposesBothFactsAsConstructed(bool $virtualRootSurvivedPrune, ?string $extractTargetId): void
    {
        $scaffolding = new RenderScaffolding($virtualRootSurvivedPrune, $extractTargetId);

        static::assertSame($virtualRootSurvivedPrune, $scaffolding->virtualRootSurvivedPrune);
        static::assertSame($extractTargetId, $scaffolding->extractTargetId);
    }

    /**
     * @return \Generator<string, array{bool, ?string}>
     */
    public static function scaffoldingProvider(): \Generator
    {
        yield 'whole-layout render, no virtual root' => [false, null];

        yield 'whole-layout render whose virtual root is still in place' => [true, null];

        yield 'partial render whose prune removed the virtual root' => [false, 'target-id'];

        yield 'partial render whose virtual root survived the prune' => [true, 'target-id'];
    }
}
