<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;

/**
 * @internal
 */
#[CoversClass(ContextDependencyAnalyzer::class)]
class ContextDependencyAnalyzerTest extends TestCase
{
    #[TestDox('returns true when element accepts context')]
    public function testRequiresParentDataReturnsTrueWhenElementAcceptsContext(): void
    {
        $element = ContentElementBuilder::create('my-component')
            ->withConsumer('product', ContextType::Single)
            ->build();

        $analyzer = new ContextDependencyAnalyzer();

        static::assertTrue($analyzer->requiresParentData($element));
    }

    #[TestDox('returns false when element has no consumers')]
    public function testRequiresParentDataReturnsFalseWhenElementHasNoConsumers(): void
    {
        $element = ContentElementBuilder::create('my-component')
            ->build();

        $analyzer = new ContextDependencyAnalyzer();

        static::assertFalse($analyzer->requiresParentData($element));
    }

    #[TestDox('returns the index of the last non-consumer element')]
    public function testFindDataRootIndexReturnsLastNonConsumerIndex(): void
    {
        $firstNonConsumer = ContentElementBuilder::create('root')->build();
        $lastNonConsumer = ContentElementBuilder::create('middle')->build();
        $consumerLeaf = ContentElementBuilder::create('leaf')
            ->withConsumer('product', ContextType::Single)
            ->build();

        $analyzer = new ContextDependencyAnalyzer();

        static::assertSame(1, $analyzer->findDataRootIndex([$firstNonConsumer, $lastNonConsumer, $consumerLeaf]));
    }

    #[TestDox('returns zero when all elements require parent data')]
    public function testFindDataRootIndexReturnsZeroWhenAllElementsRequireParentData(): void
    {
        $root = ContentElementBuilder::create('root')
            ->withConsumer('category', ContextType::Single)
            ->build();
        $child = ContentElementBuilder::create('child')
            ->withConsumer('product', ContextType::Collection)
            ->build();

        $analyzer = new ContextDependencyAnalyzer();

        static::assertSame(0, $analyzer->findDataRootIndex([$root, $child]));
    }

    #[TestDox('returns zero for an empty path')]
    public function testFindDataRootIndexReturnsZeroForEmptyPath(): void
    {
        $analyzer = new ContextDependencyAnalyzer();

        static::assertSame(0, $analyzer->findDataRootIndex([]));
    }
}
