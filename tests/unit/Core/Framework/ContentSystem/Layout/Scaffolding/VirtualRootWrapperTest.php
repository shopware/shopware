<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * The wrapper straddles the two element models, so the fixtures do too: the wrap half takes stored
 * elements, the unwrap half the rendered ones the pipeline reaches it with. Where a test needs both
 * halves it carries the real wrap output across to the rendered model, rather than hand-building a
 * second wrapper that could drift from the real one.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(VirtualRootWrapper::class)]
class VirtualRootWrapperTest extends TestCase
{
    private VirtualRootWrapper $wrapper;

    protected function setUp(): void
    {
        $this->wrapper = new VirtualRootWrapper();
    }

    /**
     * @param list<StoredElement> $elements
     */
    #[DataProvider('requiresWrappingProvider')]
    #[TestDox('reports whether wrapping is required: $_dataName')]
    public function testRequiresWrapping(bool $expected, RenderingSpecification $specification, array $elements): void
    {
        static::assertSame($expected, $this->wrapper->requiresWrapping($specification, $elements));
    }

    /**
     * @return iterable<string, array{bool, RenderingSpecification, list<StoredElement>}>
     */
    public static function requiresWrappingProvider(): iterable
    {
        yield 'data requirements and elements both present' => [
            true,
            self::specificationWithLanguageRequirement(),
            [StoredElementBuilder::create('Sw:Text')->build()],
        ];

        yield 'no data requirements' => [
            false,
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            [StoredElementBuilder::create('Sw:Text')->build()],
        ];

        yield 'no elements' => [
            false,
            self::specificationWithLanguageRequirement(),
            [],
        ];
    }

    #[TestDox('creates virtual root with correct identity and broadcast providers')]
    public function testWrapCreatesVirtualRootWithBroadcastProviders(): void
    {
        $virtualRoot = $this->wrapper->wrap(
            [StoredElementBuilder::create('Sw:Text')->build()],
            $this->specificationWithLanguageRequirement(),
        );

        static::assertSame('__page_context_root__', $virtualRoot->id);
        static::assertSame('Sw:Internal:PageContext', $virtualRoot->component);
        static::assertArrayHasKey('language', $virtualRoot->contextDefinitions->getAllProviders());
    }

    #[TestDox('holds the actual roots as a plain list under the page roots slot')]
    public function testWrapHoldsTheActualRootsAsAPlainList(): void
    {
        $root1 = StoredElementBuilder::create('Sw:Text', 'root-a')->build();
        $root2 = StoredElementBuilder::create('Sw:Image', 'root-b')->build();

        $virtualRoot = $this->wrapper->wrap([$root1, $root2], $this->specificationWithLanguageRequirement());

        static::assertSame(['__page_roots__' => [$root1, $root2]], $virtualRoot->slots);
    }

    #[TestDox('carries the placeholder values as wrapped stored property values')]
    public function testWrapCarriesPlaceholderValuesAsStoredValues(): void
    {
        $requirement = new DataRequirement('language', 'language', new LanguageLoaderConfig());
        $specification = new RenderingSpecification(
            [$requirement],
            PlaceholderValues::from(['productId' => 'product-a', 'page' => 2]),
            new Request(),
        );

        $virtualRoot = $this->wrapper->wrap([StoredElementBuilder::create('Sw:Text')->build()], $specification);

        $properties = $virtualRoot->properties();
        static::assertSame(['productId', 'page'], array_keys($properties));
        static::assertContainsOnlyInstancesOf(StoredValue::class, $properties);
        static::assertSame('product-a', $properties['productId']->asString());
        static::assertSame(2, $properties['page']->asInt());
    }

    #[TestDox('returns true when element is the virtual page context root')]
    public function testIsVirtualRootReturnsTrueForVirtualRoot(): void
    {
        $virtualRoot = $this->wrapper->wrap(
            [StoredElementBuilder::create('Sw:Text')->build()],
            $this->specificationWithLanguageRequirement(),
        );

        static::assertTrue($this->wrapper->isVirtualRoot($virtualRoot));
    }

    #[TestDox('returns false when element is a regular non-virtual element')]
    public function testIsVirtualRootReturnsFalseForRegularElement(): void
    {
        $element = StoredElementBuilder::create('Sw:Text', 'some-id')->build();

        static::assertFalse($this->wrapper->isVirtualRoot($element));
    }

    #[TestDox('extracts original roots from a valid virtual root wrapper')]
    public function testUnwrapExtractsOriginalRoots(): void
    {
        $root1 = StoredElementBuilder::create('Sw:Section', 'root-a')->build();
        $root2 = StoredElementBuilder::create('Sw:Container', 'root-b')->build();

        $virtualRoot = $this->mintRendered($this->wrapper->wrap([$root1, $root2], $this->specificationWithLanguageRequirement()));
        $extractedRoots = $this->wrapper->unwrap($virtualRoot);

        static::assertCount(2, $extractedRoots);
        static::assertSame('root-a', $extractedRoots[0]->id);
        static::assertSame('root-b', $extractedRoots[1]->id);
    }

    #[DataProvider('rootlessWrapperProvider')]
    #[TestDox('rejects a wrapper whose roots slot holds no roots')]
    public function testUnwrapRejectsAWrapperWithoutRoots(
        RenderedElement $malformedWrapper,
        ContentSystemException $expectedException
    ): void {
        // Fixture guard: the element really carries the wrapper identity the caller establishes before
        // it unwraps, so the rejection below is about the roots slot and nothing else. The check itself
        // (`isVirtualRoot()`) runs earlier in the pipeline, on the stored model, so it cannot be called
        // on this rendered fixture.
        static::assertSame(VirtualRootWrapper::VIRTUAL_ROOT_ID, $malformedWrapper->id);

        $this->expectExceptionObject($expectedException);

        $this->wrapper->unwrap($malformedWrapper);
    }

    /**
     * @return \Generator<string, array{RenderedElement, ContentSystemException}>
     */
    public static function rootlessWrapperProvider(): \Generator
    {
        yield 'missing slot' => [
            new RenderedElement(VirtualRootWrapper::VIRTUAL_ROOT_ID, 'Sw:Internal:PageContext'),
            ContentSystemException::invalidMapValue(
                'Virtual page context root slot map',
                '__page_roots__',
                'a slot holding at least one root',
                'no such slot'
            ),
        ];

        yield 'empty slot' => [
            new RenderedElement(
                VirtualRootWrapper::VIRTUAL_ROOT_ID,
                'Sw:Internal:PageContext',
                [],
                ['__page_roots__' => []],
            ),
            ContentSystemException::invalidMapValue(
                'Virtual page context root slot map',
                '__page_roots__',
                'a slot holding at least one root',
                'an empty slot'
            ),
        ];
    }

    /**
     * Mints a wrap result into the model the unwrap half speaks. `unwrap()` reads the roots slot and the
     * ids inside it and nothing else, so the crossing is done here rather than through the render step,
     * which would pull the whole rendering stack into a test with no use for it. The wrapper identity and
     * the slot name still come from the real `wrap()` output.
     */
    private function mintRendered(StoredElement $element): RenderedElement
    {
        $slots = [];
        foreach ($element->slots as $name => $children) {
            $slots[$name] = array_values(array_map(
                static fn (StoredElement $child): RenderedElement => new RenderedElement($child->id, $child->component),
                $children
            ));
        }

        return new RenderedElement($element->id, $element->component, [], $slots);
    }

    private static function specificationWithLanguageRequirement(): RenderingSpecification
    {
        $requirement = new DataRequirement('language', 'language', new LanguageLoaderConfig());

        return new RenderingSpecification(
            [$requirement],
            PlaceholderValues::from([]),
            new Request(),
        );
    }
}
