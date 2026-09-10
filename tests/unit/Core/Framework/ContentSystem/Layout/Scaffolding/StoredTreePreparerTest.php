<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\StoredTreePreparer;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Output\ElementTreePruner;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\Output\SubTreeExtractor;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfig;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredTreePreparer::class)]
class StoredTreePreparerTest extends TestCase
{
    #[TestDox('substitutes a declared token in a string property')]
    public function testPrepareResolvesStringProperties(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'Product {{productId}}')
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame('Product prod-1', $prepared[0]->property('title')?->asString());
    }

    #[TestDox('substitutes tokens in slot children at every depth')]
    public function testPrepareRecursesIntoSlotChildren(): void
    {
        $grandchild = StoredElementBuilder::create('text', 'grandchild-id')
            ->withProperty('title', 'Deep {{productId}}')
            ->build();
        $child = StoredElementBuilder::create('section', 'child-id')
            ->withSlot('default', [$grandchild])
            ->build();
        $root = StoredElementBuilder::create('section', 'root-id')
            ->withSlot('default', [$child])
            ->build();

        $prepared = $this->prepare([$root], ['productId' => 'prod-1']);

        $preparedGrandchild = $prepared[0]->slots['default'][0]->slots['default'][0];
        static::assertSame('Deep prod-1', $preparedGrandchild->property('title')?->asString());
    }

    #[TestDox('prunes away the sibling of the addressed target element while preserving the discarded subtree in the pre-prune forest')]
    public function testPreparePrunesAndPreservesPrePruneForest(): void
    {
        $prepared = $this->preparer()->prepare(
            [$this->targetAndSiblingRoot()],
            $this->targetedSpecification('target-id'),
            RenderingMode::SKELETON,
            $this->salesChannelContext()
        );

        // The target consumes context, so the prune keeps its ancestor for the data flow and drops the
        // sibling only; the pipeline's partial extract removes that ancestor after hydration.
        static::assertSame(['root-id', 'target-id'], $this->collectIds($prepared->tree));
        static::assertSame('target-id', $prepared->scaffolding->extractTargetId);
        // The sibling is what the prune drops. Wiring validation runs on this forest, so a defect in a
        // discarded subtree still has something to be judged against.
        static::assertSame(['root-id', 'target-id', 'sibling-id'], $this->collectIds($prepared->prePruneForest));
    }

    /**
     * @param scalar|null $value
     */
    #[DataProvider('nonStringPropertyProvider')]
    #[TestDox('leaves a $_dataName property untouched')]
    public function testPrepareLeavesNonStringPropertiesUntouched(string|int|float|bool|null $value): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('value', $value)
            ->build();

        $prepared = $this->prepare([$element], ['value' => 'substituted']);

        static::assertSame($value, $prepared[0]->property('value')?->jsonSerialize());
    }

    /**
     * @return iterable<string, array{scalar|null}>
     */
    public static function nonStringPropertyProvider(): iterable
    {
        yield 'int' => [42];
        yield 'float' => [4.2];
        yield 'bool' => [true];
        yield 'null' => [null];
    }

    #[TestDox('leaves the roots unwrapped when the specification carries no page-level data requirement')]
    public function testPrepareLeavesTheRootsUnwrappedWithoutPageLevelDataRequirements(): void
    {
        $root = StoredElementBuilder::create('section', 'root-id')->build();

        $prepared = $this->preparer()->prepare([$root], $this->specification([]), RenderingMode::SKELETON, $this->salesChannelContext());

        static::assertSame([$root], $prepared->tree);
        static::assertFalse($prepared->scaffolding->virtualRootSurvivedPrune);
    }

    /**
     * Identity is the assertion, so it holds both passes at once: neither the language map nor the token
     * survives a run that rebuilt the element, and the skeleton response reads neither.
     */
    #[TestDox('returns the tree unchanged in SKELETON mode, language maps and placeholder tokens alike')]
    public function testPrepareResolvesNothingInSkeletonMode(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'Product {{productId}}')
            ->withProperty('headline', [Defaults::LANGUAGE_SYSTEM => 'anchor copy'])
            ->build();

        $prepared = $this->preparer()->prepare(
            [$element],
            $this->specification(['productId' => 'prod-1']),
            RenderingMode::SKELETON,
            $this->salesChannelContext()
        );

        static::assertSame([$element], $prepared->tree);
    }

    #[TestDox('wraps the roots in a virtual root and carries the wrapped forest as the pre-prune forest for page-level data requirements')]
    public function testPrepareWrapsRootAndCarriesForestForPageLevelDataRequirements(): void
    {
        $root = StoredElementBuilder::create('section', 'root-id')->build();

        $prepared = $this->preparer()->prepare([$root], $this->pageContextSpecification(), RenderingMode::SKELETON, $this->salesChannelContext());

        // The wrap runs before the prune, so the wrapper is part of what validation judges.
        static::assertCount(1, $prepared->tree);
        static::assertSame(VirtualRootWrapper::VIRTUAL_ROOT_ID, $prepared->tree[0]->id);
        static::assertCount(1, $prepared->prePruneForest);
        static::assertSame(VirtualRootWrapper::VIRTUAL_ROOT_ID, $prepared->prePruneForest[0]->id);
        static::assertTrue($prepared->scaffolding->virtualRootSurvivedPrune);
    }

    #[TestDox('leaves a list property untouched, its string items included')]
    public function testPrepareDoesNotRecurseIntoListProperties(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('tags', ['Product {{productId}}', 'plain'])
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame(
            ['Product {{productId}}', 'plain'],
            $prepared[0]->property('tags')?->jsonSerialize()
        );
    }

    #[TestDox('leaves a map property untouched, its string values included')]
    public function testPrepareDoesNotRecurseIntoMapProperties(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('labels', ['headline' => 'Product {{productId}}'])
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame(
            ['headline' => 'Product {{productId}}'],
            $prepared[0]->property('labels')?->jsonSerialize()
        );
    }

    #[TestDox('leaves the element style untouched')]
    public function testPrepareLeavesStyleUntouched(): void
    {
        $style = new ElementStyle(['align' => ['sm' => 'left']]);
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', '{{productId}}')
            ->withStyle($style)
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame($style, $prepared[0]->style);
    }

    #[TestDox('leaves a token with no declared value verbatim')]
    public function testPrepareLeavesUnknownTokenVerbatim(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'Category {{categoryId}}')
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame('Category {{categoryId}}', $prepared[0]->property('title')?->asString());
    }

    #[TestDox('records that the virtual root did not survive a prune that cut it away')]
    public function testPrepareRecordsAVirtualRootThePruneRemoved(): void
    {
        $target = StoredElementBuilder::create('text', 'target-id')->build();
        $root = StoredElementBuilder::create('section', 'root-id')
            ->withSlot('default', [$target])
            ->build();
        $specification = new RenderingSpecification(
            [new DataRequirement('language', 'language', new LanguageLoaderConfig())],
            PlaceholderValues::from([]),
            new Request(),
            'target-id'
        );

        $prepared = $this->preparer()->prepare([$root], $specification, RenderingMode::SKELETON, $this->salesChannelContext());

        // Fixture guard: the target needs no parent data, so the prune really does cut above it rather
        // than there never having been a virtual root to lose.
        static::assertTrue((new VirtualRootWrapper())->requiresWrapping($specification, [$root]));
        static::assertFalse((new ContextDependencyAnalyzer())->requiresParentData($target));

        static::assertSame(['target-id'], $this->collectIds($prepared->tree));
        static::assertFalse($prepared->scaffolding->virtualRootSurvivedPrune);
    }

    #[TestDox('treats an empty target element id as no partial render at all')]
    public function testPrepareTreatsAnEmptyTargetElementIdAsNoTarget(): void
    {
        $root = $this->targetAndSiblingRoot();

        $prepared = $this->preparer()->prepare([$root], $this->targetedSpecification(''), RenderingMode::SKELETON, $this->salesChannelContext());

        static::assertNull($prepared->scaffolding->extractTargetId);
        static::assertSame([$root], $prepared->tree);
    }

    #[TestDox('leaves an empty tree of roots empty')]
    public function testPrepareHandlesAnEmptyTreeOfRoots(): void
    {
        $prepared = $this->prepare([], ['productId' => 'prod-1']);

        static::assertSame([], $prepared);
    }

    #[TestDox('leaves a token verbatim when the placeholder values map is empty')]
    public function testPrepareLeavesATokenVerbatimWithAnEmptyPlaceholderValuesMap(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'Product {{productId}}')
            ->build();

        $prepared = $this->prepare([$element], []);

        static::assertSame('Product {{productId}}', $prepared[0]->property('title')?->asString());
    }

    #[TestDox('replaces a translatable map with the value of the first chain language the map carries')]
    public function testPrepareSelectsTheFirstChainLanguageTheMapCarries(): void
    {
        // The anchor heads the map and the selected entry trails it, so map order cannot produce the answer.
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('headline', [
                Defaults::LANGUAGE_SYSTEM => 'anchor copy',
                'language-parent' => 'parent copy',
                'language-child' => 'child copy',
            ])
            ->build();

        $prepared = $this->prepare([$element], [], ['language-child', 'language-parent', Defaults::LANGUAGE_SYSTEM]);

        static::assertSame('child copy', $prepared[0]->property('headline')?->asString());
    }

    #[TestDox('walks the chain to the anchor entry when no earlier language is in the map')]
    public function testPrepareWalksTheChainToTheAnchorEntry(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('headline', [Defaults::LANGUAGE_SYSTEM => 'anchor copy'])
            ->build();

        $prepared = $this->prepare([$element], [], ['language-child', 'language-parent', Defaults::LANGUAGE_SYSTEM]);

        static::assertSame('anchor copy', $prepared[0]->property('headline')?->asString());
    }

    #[TestDox('never selects a map entry whose language is absent from the chain')]
    public function testPrepareSkipsALanguageAbsentFromTheChain(): void
    {
        // The dangling entry heads the map, so an implementation reading the map rather than the chain takes it.
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('headline', [
                'language-dangling' => 'ghost copy',
                Defaults::LANGUAGE_SYSTEM => 'anchor copy',
            ])
            ->build();

        $prepared = $this->prepare([$element], []);

        static::assertSame('anchor copy', $prepared[0]->property('headline')?->asString());
    }

    #[TestDox('leaves a declared non-translatable property whole, a language-map-shaped value included')]
    public function testPrepareLeavesADeclaredNonTranslatablePropertyWhole(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', [Defaults::LANGUAGE_SYSTEM => 'anchor copy', 'language-child' => 'child copy'])
            ->build();

        $prepared = $this->prepare([$element], [], ['language-child', Defaults::LANGUAGE_SYSTEM]);

        static::assertSame(
            [Defaults::LANGUAGE_SYSTEM => 'anchor copy', 'language-child' => 'child copy'],
            $prepared[0]->property('title')?->jsonSerialize()
        );
    }

    /**
     * Reduction applies no required rule of its own: the anchor requirement is the diagnostics gate's
     * business, so both sides of it collapse the same way and the property behaves as unset.
     */
    #[DataProvider('unfilledTranslatablePropertyProvider')]
    #[TestDox('reduces a $_dataName translatable property no chain language fills to the null variant')]
    public function testPrepareReducesAnUnfilledTranslatablePropertyToTheNullVariant(string $key): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty($key, ['language-other' => 'other copy'])
            ->build();

        $prepared = $this->prepare([$element], []);

        $value = $prepared[0]->property($key);
        // Present under its key and holding the null variant, which is the state the mint skips — an absent
        // key would be a different outcome.
        static::assertNotNull($value);
        static::assertTrue($value->isNull());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unfilledTranslatablePropertyProvider(): iterable
    {
        yield 'required' => ['requiredHeadline'];
        yield 'optional' => ['headline'];
    }

    /**
     * Placeholders substitute into string values and never descend into a map, so a token inside a
     * translation can only resolve once reduction has collapsed the map ahead of them.
     */
    #[TestDox('substitutes a token carried inside the selected translation')]
    public function testPrepareResolvesAPlaceholderInsideTheSelectedTranslation(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('headline', [Defaults::LANGUAGE_SYSTEM => 'Produkt {{productId}}'])
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame('Produkt prod-1', $prepared[0]->property('headline')?->asString());
    }

    /**
     * @param string|array<array-key, mixed> $value
     */
    #[DataProvider('nonMapTranslatableValueProvider')]
    #[TestDox('rejects a $_dataName on a translatable property as an internal fault')]
    public function testPrepareRejectsANonMapValueOnATranslatableProperty(string|array $value, string $expectedType): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('headline', $value)
            ->build();

        try {
            $this->prepare([$element], []);
            static::fail('Expected the non-map translatable value to be rejected.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::TRANSLATION_SHAPE_INVALID, $exception->getErrorCode());
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
            static::assertSame(
                \sprintf(
                    'Property "headline" of element "root-id" is translatable and must hold a language map, but holds %s.',
                    $expectedType
                ),
                $exception->getMessage()
            );
            // Every client-supplied path rejects the shape earlier, so reaching reduction with one is never
            // the client's mistake.
            static::assertFalse(ContentSystemException::isClientDefect($exception));
        }
    }

    /**
     * @return iterable<string, array{string|array<array-key, mixed>, string}>
     */
    public static function nonMapTranslatableValueProvider(): iterable
    {
        yield 'bare string' => ['plain copy', 'string'];
        yield 'list' => [['anchor copy'], 'list'];
        // The outer shape is a map; the selected entry is what reaches the encoders, so it is judged too.
        yield 'map with a nested-map entry' => [[Defaults::LANGUAGE_SYSTEM => ['inner' => 'copy']], 'a map with a non-string entry'];
        yield 'map with an integer entry' => [[Defaults::LANGUAGE_SYSTEM => 7], 'a map with a non-string entry'];
    }

    /**
     * An empty map is the shape a writer reaches for to mean "no translations", and reduction refuses it:
     * absence of the key is that meaning, so a zero-entry value is the same internal fault as any other
     * non-map. The raw `[]` reaches reduction as the list variant, because `StoredValue::fromDecoded()`
     * wraps an array whose keys are a zero-based sequence as a list and an empty array is such a sequence,
     * so the reported type is `list` rather than `get_debug_type([])`'s `array`.
     *
     * The benign contrast is a NON-empty map that merely carries no entry for a requested language: the shape
     * check passes, no chain language matches, and it collapses to the null variant instead of throwing.
     */
    #[TestDox('refuses an empty language map as an internal fault, where a map that only lacks the chain language collapses to the null variant')]
    public function testPrepareRefusesAnEmptyLanguageMap(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('headline', [])
            ->build();

        try {
            $this->prepare([$element], []);
            static::fail('Expected the empty language map to be rejected.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::TRANSLATION_SHAPE_INVALID, $exception->getErrorCode());
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
            static::assertSame(
                'Property "headline" of element "root-id" is translatable and must hold a language map, but holds list.',
                $exception->getMessage()
            );
            // Every client-supplied path rejects the empty map earlier, so reaching reduction with one is
            // never the client's mistake.
            static::assertFalse(ContentSystemException::isClientDefect($exception));
        }
    }

    private function preparer(): StoredTreePreparer
    {
        return new StoredTreePreparer(
            $this->typeRegistry(),
            new VirtualRootWrapper(),
            new PartialRenderer(new ElementTreePruner(), new ContextDependencyAnalyzer(), new SubTreeExtractor()),
        );
    }

    /**
     * Only `text` is registered, and it declares the three keys the reduction cases read: one plain string
     * and two translatable ones differing in the required flag. Every other key these fixtures store is
     * undeclared, and `section` names no type at all, which is the shape the virtual root is in too.
     */
    private function typeRegistry(): AbstractContentSystemElementTypeRegistry
    {
        return TestElementTypeRegistry::of([
            'text' => ContentSystemElementTypeSpecificationBuilder::create('text')
                ->primitive('title', 'string')
                ->primitive('headline', 'string', translatable: true)
                ->primitive('requiredHeadline', 'string', required: true, translatable: true)
                ->build(),
        ]);
    }

    /**
     * @param non-empty-list<string> $languageIdChain
     */
    private function salesChannelContext(array $languageIdChain = [Defaults::LANGUAGE_SYSTEM]): SalesChannelContext
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getLanguageIdChain')->willReturn($languageIdChain);

        return $context;
    }

    private function targetAndSiblingRoot(): StoredElement
    {
        return StoredElementBuilder::create('section', 'root-id')
            ->withSlot('default', [
                StoredElementBuilder::create('text', 'target-id')
                    ->withConsumer('product', ContextType::Single)
                    ->build(),
                StoredElementBuilder::create('text', 'sibling-id')->build(),
            ])
            ->build();
    }

    /**
     * @param list<StoredElement> $tree
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function collectIds(array $tree, array $ids = []): array
    {
        foreach ($tree as $element) {
            $ids[] = $element->id;
            foreach ($element->slots as $children) {
                $ids = $this->collectIds($children, $ids);
            }
        }

        return $ids;
    }

    /**
     * @param list<StoredElement> $tree
     * @param array<string, string|int|bool|float> $placeholderValues
     * @param non-empty-list<string> $languageIdChain
     *
     * @return list<StoredElement>
     */
    private function prepare(array $tree, array $placeholderValues, array $languageIdChain = [Defaults::LANGUAGE_SYSTEM]): array
    {
        return $this->preparer()->prepare(
            $tree,
            $this->specification($placeholderValues),
            RenderingMode::FULL,
            $this->salesChannelContext($languageIdChain)
        )->tree;
    }

    /**
     * @param array<string, string|int|bool|float> $placeholderValues
     */
    private function specification(array $placeholderValues): RenderingSpecification
    {
        return new RenderingSpecification([], PlaceholderValues::from($placeholderValues), new Request());
    }

    private function pageContextSpecification(): RenderingSpecification
    {
        return new RenderingSpecification(
            [new DataRequirement('language', 'language', new LanguageLoaderConfig())],
            PlaceholderValues::from([]),
            new Request()
        );
    }

    private function targetedSpecification(string $targetElementId): RenderingSpecification
    {
        return new RenderingSpecification([], PlaceholderValues::from([]), new Request(), $targetElementId);
    }
}
