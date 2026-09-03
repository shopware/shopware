<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextDependencyAnalyzer::class)]
class ContextDependencyAnalyzerTest extends TestCase
{
    private ContextDependencyAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new ContextDependencyAnalyzer();
    }

    #[TestDox('returns true when element accepts context')]
    public function testRequiresParentDataReturnsTrueWhenElementAcceptsContext(): void
    {
        $element = StoredElementBuilder::create('my-component')
            ->withConsumer('product', ContextType::Single)
            ->build();

        static::assertTrue($this->analyzer->requiresParentData($element));
    }

    #[TestDox('returns false when element has no consumers')]
    public function testRequiresParentDataReturnsFalseWhenElementHasNoConsumers(): void
    {
        $element = StoredElementBuilder::create('my-component')
            ->build();

        static::assertFalse($this->analyzer->requiresParentData($element));
    }

    #[TestDox('returns the index of the last non-consumer element')]
    public function testFindDataRootIndexReturnsLastNonConsumerIndex(): void
    {
        $firstNonConsumer = StoredElementBuilder::create('root')->build();
        $lastNonConsumer = StoredElementBuilder::create('middle')->build();
        $consumerLeaf = StoredElementBuilder::create('leaf')
            ->withConsumer('product', ContextType::Single)
            ->build();

        static::assertSame(1, $this->analyzer->findDataRootIndex([$firstNonConsumer, $lastNonConsumer, $consumerLeaf]));
    }

    #[TestDox('returns zero when all elements require parent data')]
    public function testFindDataRootIndexReturnsZeroWhenAllElementsRequireParentData(): void
    {
        $root = StoredElementBuilder::create('root')
            ->withConsumer('category', ContextType::Single)
            ->build();
        $child = StoredElementBuilder::create('child')
            ->withConsumer('product', ContextType::Collection)
            ->build();

        static::assertSame(0, $this->analyzer->findDataRootIndex([$root, $child]));
    }

    #[TestDox('returns zero for an empty path')]
    public function testFindDataRootIndexReturnsZeroForEmptyPath(): void
    {
        static::assertSame(0, $this->analyzer->findDataRootIndex([]));
    }
}
