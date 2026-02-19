<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\EnterTrackingVisitor;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\OrderTrackingVisitor;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\TestLoaderConfig;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\TestStruct;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ContentElement::class)]
class ContentElementTest extends TestCase
{
    private ContentElement $element;

    protected function setUp(): void
    {
        $this->element = ContentElementBuilder::create('test-component')
            ->withProperty('label', 'hello')
            ->withProperty('myStruct', new TestStruct())
            ->build();
    }

    #[TestDox('stores Struct value and scalar value separately via setProperty')]
    public function testSetProperty(): void
    {
        $struct = new TestStruct();

        $element = ContentElementBuilder::create('test-component')
            ->withProperty('myStruct', $struct)
            ->withProperty('myScalar', 'hello')
            ->build();

        $properties = $element->getProperties();

        static::assertSame($struct, $properties['myStruct']);
        static::assertSame('hello', $properties['myScalar']);
    }

    #[TestDox('returns stored property values by key via getProperty')]
    public function testGetPropertyReturnsValuesByKey(): void
    {
        $struct = new TestStruct();

        $element = ContentElementBuilder::create('test-component')
            ->withProperty('myStruct', $struct)
            ->withProperty('myScalar', 'hello')
            ->build();

        static::assertSame($struct, $element->getProperty('myStruct'));
        static::assertSame('hello', $element->getProperty('myScalar'));
    }

    #[TestDox('merges struct and non-struct properties into a single array')]
    public function testGetProperties(): void
    {
        $element = ContentElementBuilder::create('test-component')
            ->withProperty('myStruct', new TestStruct())
            ->withProperty('count', 42)
            ->withProperty('label', 'title')
            ->build();

        $properties = $element->getProperties();

        static::assertCount(3, $properties);
        static::assertArrayHasKey('myStruct', $properties);
        static::assertArrayHasKey('count', $properties);
        static::assertArrayHasKey('label', $properties);
    }

    #[TestDox('clears all previous struct and non-struct properties when setProperties is called')]
    public function testSetProperties(): void
    {
        $element = ContentElementBuilder::create('test-component')
            ->withProperty('old', 'value')
            ->build();

        static::assertTrue($element->hasProperty('old'));

        $element->setProperties(['new' => 'replacement']);

        static::assertFalse($element->hasProperty('old'));
        static::assertTrue($element->hasProperty('new'));
        static::assertSame('replacement', $element->getProperty('new'));
    }

    /**
     * @return \Generator<string, array{string, bool}>
     */
    public static function hasPropertyProvider(): \Generator
    {
        yield 'existing scalar property' => ['label', true];
        yield 'existing struct property' => ['myStruct', true];
        yield 'missing property' => ['nonexistent', false];
    }

    #[DataProvider('hasPropertyProvider')]
    #[TestDox('returns correct boolean for hasProperty when key is $key')]
    public function testHasPropertyReturnsTrueForExistingAndFalseForMissing(string $key, bool $expected): void
    {
        static::assertSame($expected, $this->element->hasProperty($key));
    }

    #[TestDox('returns true when at least one data requirement is declared')]
    public function testRequiresDataReturnsTrueWhenRequirementsExist(): void
    {
        $element = ContentElementBuilder::create('test-component')
            ->withDataRequirement('product', 'entity', new TestLoaderConfig())
            ->build();

        static::assertTrue($element->requiresData());
    }

    #[TestDox('returns false when no data requirements are declared')]
    public function testRequiresDataReturnsFalseWhenNoRequirements(): void
    {
        $element = ContentElementBuilder::create('test-component')->build();

        static::assertFalse($element->requiresData());
    }

    #[TestDox('yields all direct child elements across all slots')]
    public function testAllSlotElementsYieldsChildrenAcrossAllSlots(): void
    {
        $child1 = ContentElementBuilder::create('child-a')->build();
        $child2 = ContentElementBuilder::create('child-b')->build();
        $child3 = ContentElementBuilder::create('child-c')->build();

        $element = ContentElementBuilder::create('parent')
            ->withSlot('slot-1', [$child1, $child2])
            ->withSlot('slot-2', [$child3])
            ->build();

        $collected = [];
        foreach ($element->allSlotElements() as $child) {
            $collected[] = $child;
        }

        static::assertCount(3, $collected);
        static::assertContains($child1, $collected);
        static::assertContains($child2, $collected);
        static::assertContains($child3, $collected);
    }

    #[TestDox('yields nothing when element has no slots')]
    public function testAllSlotElementsYieldsNothingWhenNoSlots(): void
    {
        $element = ContentElementBuilder::create('leaf-component')->build();

        $collected = [];
        foreach ($element->allSlotElements() as $child) {
            $collected[] = $child;
        }

        static::assertSame([], $collected);
    }

    #[TestDox('calls enter before children and leave after children in depth-first order')]
    public function testTraverseCallsEnterAndLeaveInDepthFirstOrder(): void
    {
        $child = ContentElementBuilder::create('child')->build();
        $parent = ContentElementBuilder::create('parent')
            ->withSlot('default', [$child])
            ->build();

        $visitor = new OrderTrackingVisitor();

        $parent->traverse($visitor);

        static::assertSame(
            ['enter:parent', 'enter:child', 'leave:child', 'leave:parent'],
            $visitor->log
        );
    }

    #[TestDox('visits all nested children recursively in depth-first order')]
    public function testTraverseVisitsNestedChildrenRecursively(): void
    {
        $grandchild = ContentElementBuilder::create('grandchild')->build();
        $child = ContentElementBuilder::create('child')
            ->withSlot('default', [$grandchild])
            ->build();
        $parent = ContentElementBuilder::create('parent')
            ->withSlot('default', [$child])
            ->build();

        $visitor = new EnterTrackingVisitor();

        $parent->traverse($visitor);

        static::assertSame(['parent', 'child', 'grandchild'], $visitor->visited);
    }

    /**
     * @return \Generator<string, array{string, string, bool}>
     */
    public static function acceptsContextProvider(): \Generator
    {
        yield 'exact key match returns true' => ['product', 'product', true];
        yield 'prefix path match returns true' => ['product', 'product.cover', true];
        yield 'deep prefix path match returns true' => ['product', 'product.manufacturer.name', true];
        yield 'unrelated key returns false' => ['product', 'category', false];
        yield 'partial prefix without dot returns false' => ['product', 'productName', false];
    }

    #[DataProvider('acceptsContextProvider')]
    #[TestDox('returns $expected when checking if element accepts context key "$providerKey" with consumer "$consumerKey"')]
    public function testAcceptsContextReturnsTrueForExactAndPrefixMatchFalseForUnrelated(
        string $providerKey,
        string $consumerKey,
        bool $expected
    ): void {
        $element = ContentElementBuilder::create('test-component')
            ->withConsumer($consumerKey, ContextType::Single)
            ->build();

        $pathResolver = new ContextPathResolver();

        static::assertSame($expected, $element->acceptsContext($providerKey, $pathResolver));
    }

    #[TestDox('returns only direct children that accept the given context key')]
    public function testCollectConsumersReturnsDirectChildrenMatchingContextKey(): void
    {
        $consumer = ContentElementBuilder::create('consumer-element')
            ->withConsumer('product', ContextType::Single)
            ->build();

        $nonConsumer = ContentElementBuilder::create('non-consumer-element')
            ->build();

        $parent = ContentElementBuilder::create('parent')
            ->withSlot('default', [$consumer, $nonConsumer])
            ->build();

        $pathResolver = new ContextPathResolver();
        $result = $parent->collectConsumers('product', $pathResolver);

        static::assertCount(1, $result);
        static::assertSame($consumer, $result[0]);
    }

    #[TestDox('substitutes placeholder tokens in string properties with resolved values')]
    public function testReplacePlaceholdersSubstitutesValuesInStringProperties(): void
    {
        $element = ContentElementBuilder::create('test-component')
            ->withProperty('title', 'Hello {{name}}!')
            ->withProperty('description', 'Product: {{productId}}')
            ->build();

        $specification = $this->createRenderingSpecification(['name' => 'World', 'productId' => 'abc123']);

        $element->replacePlaceholders($specification);

        static::assertSame('Hello World!', $element->getProperty('title'));
        static::assertSame('Product: abc123', $element->getProperty('description'));
    }

    #[TestDox('leaves non-string property values unchanged during placeholder replacement')]
    public function testReplacePlaceholdersIgnoresNonStringProperties(): void
    {
        $element = ContentElementBuilder::create('test-component')
            ->withProperty('count', 42)
            ->withProperty('enabled', true)
            ->build();

        $specification = $this->createRenderingSpecification(['count' => 'replaced']);

        $element->replacePlaceholders($specification);

        static::assertSame(42, $element->getProperty('count'));
        static::assertTrue($element->getProperty('enabled'));
    }

    #[TestDox('recursively replaces placeholders in children within slots')]
    public function testReplacePlaceholdersRecursesIntoSlotChildren(): void
    {
        $child = ContentElementBuilder::create('child-component')
            ->withProperty('label', 'Item: {{itemId}}')
            ->build();

        $parent = ContentElementBuilder::create('parent-component')
            ->withSlot('default', [$child])
            ->build();

        $specification = $this->createRenderingSpecification(['itemId' => '42']);

        $parent->replacePlaceholders($specification);

        static::assertSame('Item: 42', $child->getProperty('label'));
    }

    #[TestDox('merges struct and non-struct properties into properties key and excludes internal arrays')]
    public function testJsonSerialize(): void
    {
        $struct = new TestStruct();

        $element = ContentElementBuilder::create('test-component', 'test-id')
            ->withProperty('myStruct', $struct)
            ->withProperty('title', 'hello')
            ->build();

        $data = $element->jsonSerialize();

        static::assertArrayNotHasKey('structProperties', $data);
        static::assertArrayNotHasKey('nonStructProperties', $data);
        static::assertArrayHasKey('properties', $data);
        static::assertSame($struct, $data['properties']['myStruct']);
        static::assertSame('hello', $data['properties']['title']);
    }

    /**
     * @param array<string, string|int|bool|float> $values
     */
    private function createRenderingSpecification(array $values): RenderingSpecification
    {
        return new RenderingSpecification(
            'layout-id',
            [],
            PlaceholderValues::from($values),
            new Request(),
        );
    }
}
