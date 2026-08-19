<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\OrderTrackingVisitor;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentElement::class)]
class ContentElementTest extends TestCase
{
    #[TestDox('stores Struct value and scalar value separately via setProperty')]
    public function testSetPropertyDispatchesStructAndScalarToDifferentBuckets(): void
    {
        $struct = new StubStruct();

        $element = $this->buildElementWithMixedProperties($struct);

        $properties = $element->getProperties();

        static::assertSame($struct, $properties['myStruct']);
        static::assertSame('hello', $properties['myScalar']);
    }

    #[TestDox('returns stored property values by key via getProperty')]
    public function testGetPropertyReturnsValuesByKey(): void
    {
        $struct = new StubStruct();

        $element = $this->buildElementWithMixedProperties($struct);

        static::assertSame($struct, $element->getProperty('myStruct'));
        static::assertSame('hello', $element->getProperty('myScalar'));
    }

    #[TestDox('merges struct and non-struct properties into a single array')]
    public function testGetPropertiesReturnsMergedStructAndNonStructMap(): void
    {
        $element = ContentElementBuilder::create('test-component')
            ->withProperty('myStruct', new StubStruct())
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
    public function testSetPropertiesClearsExistingAndAppliesNewValues(): void
    {
        $element = ContentElementBuilder::create('test-component')
            ->withProperty('old', 'value')
            ->build();

        $element->setProperties(['new' => 'replacement']);

        static::assertFalse($element->hasProperty('old'));
        static::assertTrue($element->hasProperty('new'));
        static::assertSame('replacement', $element->getProperty('new'));
    }

    #[DataProvider('returnsCorrectBooleanForHasPropertyProvider')]
    #[TestDox('returns correct boolean for hasProperty when key is $key')]
    public function testHasPropertyReturnsTrueForExistingAndFalseForMissing(string $key, bool $expected): void
    {
        $element = ContentElementBuilder::create('test-component')
            ->withProperty('label', 'hello')
            ->withProperty('myStruct', new StubStruct())
            ->build();

        static::assertSame($expected, $element->hasProperty($key));
    }

    #[TestDox('returns null when property key does not exist')]
    public function testGetPropertyReturnsNullForMissingKey(): void
    {
        $element = ContentElementBuilder::create('test-component')->build();

        static::assertNull($element->getProperty('nonexistent'));
    }

    #[TestDox('returns true when at least one data requirement is declared')]
    public function testRequiresDataReturnsTrueWhenRequirementsExist(): void
    {
        $element = ContentElementBuilder::create('test-component')
            ->withDataRequirement('product', 'entity', new StubLoaderConfig())
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

    #[TestDox('traverses tree depth-first calling enter before children and leave after')]
    public function testTraverseCallsVisitorInDepthFirstOrder(): void
    {
        $grandchild = ContentElementBuilder::create('grandchild')->build();
        $child = ContentElementBuilder::create('child')
            ->withSlot('default', [$grandchild])
            ->build();
        $parent = ContentElementBuilder::create('parent')
            ->withSlot('default', [$child])
            ->build();

        $visitor = new OrderTrackingVisitor();

        $parent->traverse($visitor);

        static::assertSame(
            ['enter:parent', 'enter:child', 'enter:grandchild', 'leave:grandchild', 'leave:child', 'leave:parent'],
            $visitor->log
        );
    }

    #[TestDox('merges struct and non-struct properties into properties key and excludes internal arrays')]
    public function testJsonSerializeExposesPropertiesKeyAndHidesInternalArrays(): void
    {
        $struct = new StubStruct();

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

    #[TestDox('includes the style array in serialized output when the element carries a non-empty style')]
    public function testIncludesStyleInSerializedOutputWhenNonEmpty(): void
    {
        $element = ContentElementBuilder::create('test-component', 'test-id')
            ->withStyle(new ElementStyle(['col-span' => ['md' => 6]]))
            ->build();

        static::assertSame(['col-span' => ['md' => 6]], $element->jsonSerialize()['style']);
    }

    #[TestDox('omits optional keys from serialized output when the element has no data requirements, slots, context, style, or attributed specifications')]
    public function testOmitsOptionalKeysFromSerializedOutputWhenEmpty(): void
    {
        $data = ContentElementBuilder::create('test-component', 'test-id')->build()->jsonSerialize();

        static::assertArrayNotHasKey('style', $data);
        static::assertArrayNotHasKey('dataRequirements', $data);
        static::assertArrayNotHasKey('slots', $data);
        static::assertArrayNotHasKey('providesContext', $data);
        static::assertArrayNotHasKey('acceptsContext', $data);
        static::assertArrayNotHasKey('attributedSpecifications', $data);

        // NEVER-emitted keys — Struct internals and API-alias must never appear
        static::assertArrayNotHasKey('extensions', $data);
        static::assertArrayNotHasKey('apiAlias', $data);
        static::assertArrayNotHasKey('contextDefinitions', $data);
    }

    #[TestDox('omits attributedSpecifications from serialized output even when the element carries non-empty attribution')]
    public function testOmitsAttributedSpecificationsFromSerializedOutputWhenNonEmpty(): void
    {
        $element = ContentElementBuilder::create('test-component', 'test-id')
            ->withAttributedSpecification('product', 'binding-spec-1')
            ->withAttributedSpecification('category', 'binding-spec-2')
            ->build();

        static::assertArrayNotHasKey('attributedSpecifications', $element->jsonSerialize());
    }

    #[TestDox('omits attributedSpecifications from serialized slot children even when they carry non-empty attribution')]
    public function testOmitsAttributedSpecificationsFromNestedSlotChildrenInSerializedOutput(): void
    {
        $child = ContentElementBuilder::create('child-component')
            ->withAttributedSpecification('product', 'binding-spec-1')
            ->build();

        $parent = ContentElementBuilder::create('parent-component')
            ->withSlot('default', [$child])
            ->build();

        $childData = $parent->jsonSerialize()['slots']['default'][0];

        static::assertArrayNotHasKey('attributedSpecifications', $childData);
    }

    #[TestDox('providers and consumers survive jsonSerialize and are reconstructed under providesContext and acceptsContext')]
    public function testContextSurvivesJsonSerializationAsProvidedAndAcceptedContext(): void
    {
        $element = ContentElementBuilder::create('test-component', 'test-id')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withConsumer('category', ContextType::Single, required: true)
            ->build();

        $data = $element->jsonSerialize();

        static::assertArrayHasKey('providesContext', $data);
        static::assertArrayHasKey('acceptsContext', $data);

        // Provider: flat shape — type + distribution config spread in
        // BroadcastDistributionConfig::toArray() emits consumerAlias unconditionally (null here)
        static::assertSame(
            ['type' => 'single', 'distribution' => 'broadcast', 'consumerAlias' => null],
            $data['providesContext']['product']
        );

        // Consumer: required flag present, optional fields omitted when false/null
        static::assertSame(
            ['type' => 'single', 'required' => true],
            $data['acceptsContext']['category']
        );

        // context definitions must NOT be leaked under the old key
        static::assertArrayNotHasKey('contextDefinitions', $data);
    }

    /**
     * @return \Generator<string, array{string, bool}>
     */
    public static function returnsCorrectBooleanForHasPropertyProvider(): \Generator
    {
        yield 'existing scalar property' => ['label', true];
        yield 'existing struct property' => ['myStruct', true];
        yield 'missing property' => ['nonexistent', false];
    }

    private function buildElementWithMixedProperties(StubStruct $struct): ContentElement
    {
        return ContentElementBuilder::create('test-component')
            ->withProperty('myStruct', $struct)
            ->withProperty('myScalar', 'hello')
            ->build();
    }
}
