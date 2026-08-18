<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\RenderScaffolding;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\TreePreparationResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TreePreparationResult::class)]
class TreePreparationResultTest extends TestCase
{
    #[TestDox('keeps the post-prune tree and the pre-prune forest apart')]
    public function testKeepsThePrunedTreeAndThePrePruneForestApart(): void
    {
        $target = StoredElementBuilder::create('text', 'target-id')->build();
        $root = StoredElementBuilder::create('section', 'root-id')
            ->withSlot('default', [$target])
            ->build();
        $scaffolding = new RenderScaffolding(false, 'target-id');

        $result = new TreePreparationResult([$target], [$root], $scaffolding);

        static::assertSame([$target], $result->tree);
        static::assertSame([$root], $result->prePruneForest);
        static::assertSame($scaffolding, $result->scaffolding);
    }
}
