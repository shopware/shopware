<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextResolutionVisitor;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IteratorDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\SlicedDistributionConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubContextStruct;

/**
 * @internal
 */
#[Package('framework')]
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

    #[TestDox('reads the value from the provider key and delivers it to children matching the consumer alias')]
    public function testProviderConsumerAliasSelectsChildrenIndependentlyOfTheProviderKey(): void
    {
        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('product', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('featuredProduct', 'product-data')
            ->withProvider('featuredProduct', BroadcastDistributionConfig::aliased('product'))
            ->withSlot('default', [$child])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame('product-data', $child->getProperty('product'));
    }

    #[TestDox('resolves nested Struct property via dot notation')]
    public function testResolvesNestedStructPropertyViaDotNotation(): void
    {
        $coverStruct = new StubContextStruct('cover-url');

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

        static::assertFalse($child->hasProperty('product'), 'a null provider value must write no key at all, not a null under the key');
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

        static::assertTrue($child->hasProperty('product.cover'), 'the consumer key must be present holding null, not absent');
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

    #[TestDox('indexed distribution writes an explicit null for a consumer past the end of the data')]
    public function testIndexedDistributionWritesExplicitNullForConsumerBeyondTheData(): void
    {
        $child1 = ContentElementBuilder::create('child-1', 'c1')
            ->withConsumer('items', ContextType::Single)
            ->build();

        $child2 = ContentElementBuilder::create('child-2', 'c2')
            ->withConsumer('items', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('items', ['first-item'])
            ->withProvider('items', IndexedDistributionConfig::simple())
            ->withSlot('default', [$child1, $child2])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame('first-item', $child1->getProperty('items'));
        static::assertTrue($child2->hasProperty('items'), 'the under-supplied consumer key must be present holding null, not absent');
        static::assertNull($child2->getProperty('items'));
    }

    #[TestDox('indexed distribution writes null to every consumer, including the first, when the provider data is not an array')]
    public function testIndexedDistributionWritesNullToEveryConsumerWhenTheDataIsNotAnArray(): void
    {
        $child1 = ContentElementBuilder::create('child-1', 'c1')
            ->withConsumer('items', ContextType::Single)
            ->build();

        $child2 = ContentElementBuilder::create('child-2', 'c2')
            ->withConsumer('items', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('items', 'not-an-array')
            ->withProvider('items', IndexedDistributionConfig::simple())
            ->withSlot('default', [$child1, $child2])
            ->build();

        $parent->traverse($this->visitor);

        static::assertTrue($child1->hasProperty('items'), 'the first consumer key must be present holding null, not absent');
        static::assertNull($child1->getProperty('items'), 'wrong-shape data nulls the first consumer too, unlike under-supply');
        static::assertTrue($child2->hasProperty('items'));
        static::assertNull($child2->getProperty('items'));
    }

    #[TestDox('keyed distribution writes an explicit null for a consumer whose key finds nothing')]
    public function testKeyedDistributionWritesExplicitNullForConsumerWhoseKeyFindsNothing(): void
    {
        $matching = ContentElementBuilder::create('child-1', 'c1')
            ->withProperty('data_key', 'present')
            ->withConsumer('items', ContextType::Single)
            ->build();

        $missing = ContentElementBuilder::create('child-2', 'c2')
            ->withProperty('data_key', 'absent')
            ->withConsumer('items', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('items', ['present' => 'present-item'])
            ->withProvider('items', KeyedDistributionConfig::simple())
            ->withSlot('default', [$matching, $missing])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame('present-item', $matching->getProperty('items'));
        static::assertTrue($missing->hasProperty('items'), 'the missed consumer key must be present holding null, not absent');
        static::assertNull($missing->getProperty('items'));
    }

    #[TestDox('keyed distribution writes null to every consumer, even a key-matching one, when the provider data is not an array')]
    public function testKeyedDistributionWritesNullToEveryConsumerWhenTheDataIsNotAnArray(): void
    {
        $matching = ContentElementBuilder::create('child-1', 'c1')
            ->withProperty('data_key', 'present')
            ->withConsumer('items', ContextType::Single)
            ->build();

        $missing = ContentElementBuilder::create('child-2', 'c2')
            ->withProperty('data_key', 'absent')
            ->withConsumer('items', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('items', 'not-an-array')
            ->withProvider('items', KeyedDistributionConfig::simple())
            ->withSlot('default', [$matching, $missing])
            ->build();

        $parent->traverse($this->visitor);

        static::assertTrue($matching->hasProperty('items'), 'the key-matching consumer key must be present holding null, not absent');
        static::assertNull($matching->getProperty('items'), 'wrong-shape data bypasses key matching entirely');
        static::assertTrue($missing->hasProperty('items'));
        static::assertNull($missing->getProperty('items'));
    }

    #[TestDox('sliced distribution writes an empty array, not null, for a consumer past the last slice')]
    public function testSlicedDistributionWritesEmptyArrayForConsumerBeyondTheSlices(): void
    {
        $child1 = ContentElementBuilder::create('child-1', 'c1')
            ->withConsumer('items', ContextType::Collection)
            ->build();

        $child2 = ContentElementBuilder::create('child-2', 'c2')
            ->withConsumer('items', ContextType::Collection)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('items', ['first-item'])
            ->withProvider('items', SlicedDistributionConfig::withSliceSize(1))
            ->withSlot('default', [$child1, $child2])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame(['first-item'], $child1->getProperty('items'));
        static::assertSame([], $child2->getProperty('items'));
    }

    #[TestDox('sliced distribution writes an empty array to every consumer, including the first, when the provider data is not an array')]
    public function testSlicedDistributionWritesAnEmptyArrayToEveryConsumerWhenTheDataIsNotAnArray(): void
    {
        $child1 = ContentElementBuilder::create('child-1', 'c1')
            ->withConsumer('items', ContextType::Collection)
            ->build();

        $child2 = ContentElementBuilder::create('child-2', 'c2')
            ->withConsumer('items', ContextType::Collection)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('items', 'not-an-array')
            ->withProvider('items', SlicedDistributionConfig::withSliceSize(1))
            ->withSlot('default', [$child1, $child2])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame([], $child1->getProperty('items'), 'wrong-shape data empties the first consumer too, and writes an array rather than null');
        static::assertSame([], $child2->getProperty('items'));
    }

    #[TestDox('iterator distribution writes nothing at all for a consumer past the end of the data')]
    public function testIteratorDistributionWritesNothingForConsumerBeyondTheData(): void
    {
        $child1 = ContentElementBuilder::create('child-1', 'c1')
            ->withConsumer('items', ContextType::Single)
            ->build();

        $child2 = ContentElementBuilder::create('child-2', 'c2')
            ->withConsumer('items', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('items', ['first-item'])
            ->withProvider('items', IteratorDistributionConfig::simple())
            ->withSlot('default', [$child1, $child2])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame('first-item', $child1->getProperty('items'));
        static::assertFalse($child2->hasProperty('items'), 'the under-supplied consumer key must stay absent, not be written as null');
    }

    #[TestDox('iterator distribution writes nothing at all to any consumer when the provider data is not an array')]
    public function testIteratorDistributionWritesNothingAtAllWhenTheDataIsNotAnArray(): void
    {
        $child1 = ContentElementBuilder::create('child-1', 'c1')
            ->withConsumer('items', ContextType::Single)
            ->build();

        $child2 = ContentElementBuilder::create('child-2', 'c2')
            ->withConsumer('items', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('items', 'not-an-array')
            ->withProvider('items', IteratorDistributionConfig::simple())
            ->withSlot('default', [$child1, $child2])
            ->build();

        $parent->traverse($this->visitor);

        static::assertFalse($child1->hasProperty('items'), 'wrong-shape data leaves the first consumer key absent too, unlike under-supply');
        static::assertFalse($child2->hasProperty('items'));
    }

    #[TestDox('the last provider wins when two providers on one parent deliver to the same consumer key')]
    public function testLastProviderWinsWhenTwoProvidersDeliverToTheSameConsumerKey(): void
    {
        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('product', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('firstSource', 'first-data')
            ->withProperty('secondSource', 'second-data')
            ->withProvider('firstSource', BroadcastDistributionConfig::aliased('product'))
            ->withProvider('secondSource', BroadcastDistributionConfig::aliased('product'))
            ->withSlot('default', [$child])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame('second-data', $child->getProperty('product'));
    }

    #[TestDox('one delivery fills every matching consumer key on the same child')]
    public function testOneDeliveryFillsEveryMatchingConsumerKeyOnTheSameChild(): void
    {
        $productStruct = new StubContextStruct('cover-url');

        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('product', ContextType::Single)
            ->withConsumer('product.cover', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('product', $productStruct)
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame($productStruct, $child->getProperty('product'));
        static::assertSame('cover-url', $child->getProperty('product.cover'));
    }

    #[TestDox('pools consumers across all slots of the parent in slot-then-index order')]
    public function testPoolsConsumersAcrossAllSlotsInSlotThenIndexOrder(): void
    {
        $first = ContentElementBuilder::create('child-1', 'c1')
            ->withConsumer('items', ContextType::Single)
            ->build();

        $second = ContentElementBuilder::create('child-2', 'c2')
            ->withConsumer('items', ContextType::Single)
            ->build();

        $third = ContentElementBuilder::create('child-3', 'c3')
            ->withConsumer('items', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('items', ['first-item', 'second-item', 'third-item'])
            ->withProvider('items', IndexedDistributionConfig::simple())
            ->withSlot('left', [$first, $second])
            ->withSlot('right', [$third])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame('first-item', $first->getProperty('items'));
        static::assertSame('second-item', $second->getProperty('items'));
        static::assertSame('third-item', $third->getProperty('items'));
    }

    #[TestDox('a subpath consumer occupies its own position in an indexed distribution')]
    public function testSubpathConsumerOccupiesItsOwnPositionInIndexedDistribution(): void
    {
        $firstProduct = new StubContextStruct('first-cover');
        $secondProduct = new StubContextStruct('second-cover');

        $exact = ContentElementBuilder::create('child-1', 'c1')
            ->withConsumer('product', ContextType::Single)
            ->build();

        $subpath = ContentElementBuilder::create('child-2', 'c2')
            ->withConsumer('product.cover', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('product', [$firstProduct, $secondProduct])
            ->withProvider('product', IndexedDistributionConfig::simple())
            ->withSlot('default', [$exact, $subpath])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame($firstProduct, $exact->getProperty('product'));
        static::assertSame('second-cover', $subpath->getProperty('product.cover'));
    }
}
