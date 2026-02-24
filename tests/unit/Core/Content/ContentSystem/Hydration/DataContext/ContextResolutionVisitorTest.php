<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextResolutionVisitor;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\TestContextStruct;

/**
 * @internal
 */
#[CoversClass(ContextResolutionVisitor::class)]
class ContextResolutionVisitorTest extends TestCase
{
    private ContextResolutionVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new ContextResolutionVisitor(new ContextPathResolver());
    }

    #[TestDox('distributes broadcast context data to all direct children consumers')]
    public function testDistributesBroadcastContextToAllDirectChildren(): void
    {
        $child1 = ContentElementBuilder::create('child-1', 'c1')
            ->withConsumer('product', ContextType::Single)
            ->build();

        $child2 = ContentElementBuilder::create('child-2', 'c2')
            ->withConsumer('product', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('product', 'product-data')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child1, $child2])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame('product-data', $child1->getProperty('product'));
        static::assertSame('product-data', $child2->getProperty('product'));
    }

    #[TestDox('does not distribute context to children that are not consumers of the key')]
    public function testDoesNotDistributeToNonConsumerChildren(): void
    {
        $nonConsumer = ContentElementBuilder::create('text', 'nc1')->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('product', 'product-data')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$nonConsumer])
            ->build();

        $parent->traverse($this->visitor);

        static::assertNull($nonConsumer->getProperty('product'));
    }

    #[TestDox('applies property alias on consumer, storing data under the alias key')]
    public function testAppliesPropertyAliasOnConsumer(): void
    {
        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('product', ContextType::Single, propertyAlias: 'myProduct')
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('product', 'product-data')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame('product-data', $child->getProperty('myProduct'));
    }

    #[TestDox('resolves nested Struct property via dot notation')]
    public function testResolvesNestedStructPropertyViaDotNotation(): void
    {
        $coverStruct = new TestContextStruct('cover-url');

        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('product.cover', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('product', $coverStruct)
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame('cover-url', $child->getProperty('product.cover'));
    }

    #[TestDox('skips non-matching consumer context keys and sets only the matching one')]
    public function testSkipsNonMatchingConsumerContextKeys(): void
    {
        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('product', ContextType::Single)
            ->withConsumer('category', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('product', 'product-data')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame('product-data', $child->getProperty('product'));
        static::assertNull($child->getProperty('category'));
    }

    #[TestDox('skips distribution when provider data property is null')]
    public function testSkipsDistributionWhenProviderDataIsNull(): void
    {
        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('product', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $parent->traverse($this->visitor);

        static::assertNull($child->getProperty('product'));
    }

    #[TestDox('sets null for optional consumer when distributed data is not a Struct')]
    public function testSetsNullForOptionalConsumerWhenPathNotResolvable(): void
    {
        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('product.cover', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('product', 'not-a-struct')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $parent->traverse($this->visitor);

        static::assertNull($child->getProperty('product.cover'));
    }

    #[TestDox('throws for required consumer when distributed data is not a Struct and path needs resolution')]
    public function testThrowsForRequiredConsumerWhenPathNotResolvable(): void
    {
        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('product.cover', ContextType::Single, required: true)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('product', 'not-a-struct')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $this->expectExceptionObject(ContentSystemException::contextPathNotResolvable(
            'product.cover',
            'c1',
            'Context data is not a Struct instance'
        ));

        $parent->traverse($this->visitor);
    }
}
