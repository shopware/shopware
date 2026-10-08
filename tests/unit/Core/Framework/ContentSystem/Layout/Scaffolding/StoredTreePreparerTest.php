<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationLoaderConfig;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationLoaderConfigSerializer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
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
use Symfony\Component\DependencyInjection\ServiceLocator;
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
     * @param list<string>|array<string, string> $value
     */
    #[DataProvider('containerPropertyProvider')]
    #[TestDox('leaves a $_dataName property untouched, the strings inside it included')]
    public function testPrepareLeavesAContainerPropertyUntouched(string $key, array $value): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty($key, $value)
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame($value, $prepared[0]->property($key)?->jsonSerialize());
    }

    /**
     * @return iterable<string, array{string, list<string>|array<string, string>}>
     */
    public static function containerPropertyProvider(): iterable
    {
        yield 'list' => ['tags', ['Product {{productId}}', 'plain']];
        yield 'map' => ['labels', ['headline' => 'Product {{productId}}']];
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

    /**
     * @param array<string, string> $placeholderValues
     */
    #[DataProvider('unresolvedTokenProvider')]
    #[TestDox('leaves a token verbatim when $_dataName')]
    public function testPrepareLeavesAnUnresolvedTokenVerbatim(string $title, array $placeholderValues): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', $title)
            ->build();

        $prepared = $this->prepare([$element], $placeholderValues);

        static::assertSame($title, $prepared[0]->property('title')?->asString());
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function unresolvedTokenProvider(): iterable
    {
        yield 'no declared value matches it' => ['Category {{categoryId}}', ['productId' => 'prod-1']];
        yield 'the placeholder values map is empty' => ['Product {{productId}}', []];
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

    /**
     * @param array<string, string> $map
     * @param non-empty-list<string> $languageIdChain
     */
    #[DataProvider('chainSelectionProvider')]
    #[TestDox('reduces a translatable map to $_dataName')]
    public function testPrepareSelectsTheChainLanguageEntryOfAStringMap(array $map, array $languageIdChain, string $expected): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('headline', $map)
            ->build();

        $prepared = $this->prepare([$element], [], $languageIdChain);

        static::assertSame($expected, $prepared[0]->property('headline')?->asString());
    }

    /**
     * @return iterable<string, array{array<string, string>, non-empty-list<string>, string}>
     */
    public static function chainSelectionProvider(): iterable
    {
        // The anchor heads the map and the selected entry trails it, so map order cannot produce the answer.
        yield 'the entry of the first chain language it carries' => [
            [
                Defaults::LANGUAGE_SYSTEM => 'anchor copy',
                'language-parent' => 'parent copy',
                'language-child' => 'child copy',
            ],
            ['language-child', 'language-parent', Defaults::LANGUAGE_SYSTEM],
            'child copy',
        ];

        // The dangling entry heads the map, so an implementation reading the map rather than the chain takes it.
        yield 'the anchor entry when a language outside the chain heads the map' => [
            [
                'language-dangling' => 'ghost copy',
                Defaults::LANGUAGE_SYSTEM => 'anchor copy',
            ],
            [Defaults::LANGUAGE_SYSTEM],
            'anchor copy',
        ];
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

    /**
     * The selected entry is served verbatim in its declared primitive. The anchor heads each map and the selected
     * entry trails it, so map order cannot produce the answer.
     *
     * @param array<string, int|float|bool> $map
     */
    #[DataProvider('typedTranslationProvider')]
    #[TestDox('selects the chain language entry of a $_dataName')]
    public function testPrepareSelectsTheChainLanguageEntryOfATypedTranslatableProperty(string $key, array $map, int|float|bool $expected): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty($key, $map)
            ->build();

        $prepared = $this->prepare([$element], [], ['language-child', Defaults::LANGUAGE_SYSTEM]);

        static::assertSame($expected, $prepared[0]->property($key)?->jsonSerialize());
    }

    /**
     * @return iterable<string, array{string, array<string, int|float|bool>, int|float|bool}>
     */
    public static function typedTranslationProvider(): iterable
    {
        // The same integer entry the translatable string `headline` rejects below.
        yield 'translatable integer' => ['count', [Defaults::LANGUAGE_SYSTEM => 3, 'language-child' => 7], 7];

        // `false` is a value to serve, not an absent one: a truthiness test would fall through to the anchor.
        yield 'translatable boolean holding false' => ['visible', [Defaults::LANGUAGE_SYSTEM => true, 'language-child' => false], false];

        yield 'translatable number holding an integer entry' => ['ratio', [Defaults::LANGUAGE_SYSTEM => 1.5, 'language-child' => 2], 2];
    }

    #[TestDox('reduces a registered slot child under a parent whose component no type declares, leaving the parent whole')]
    public function testPrepareReducesASlotChildUnderAnUnregisteredParent(): void
    {
        $child = StoredElementBuilder::create('text', 'child-id')
            ->withProperty('headline', [Defaults::LANGUAGE_SYSTEM => 'anchor copy', 'language-child' => 'child copy'])
            ->build();
        $parent = StoredElementBuilder::create('section', 'root-id')
            ->withProperty('headline', [Defaults::LANGUAGE_SYSTEM => 'parent anchor copy', 'language-child' => 'parent child copy'])
            ->withSlot('default', [$child])
            ->build();

        $prepared = $this->prepare([$parent], [], ['language-child', Defaults::LANGUAGE_SYSTEM]);

        static::assertSame('child copy', $prepared[0]->slots['default'][0]->property('headline')?->asString());
        static::assertSame(
            [Defaults::LANGUAGE_SYSTEM => 'parent anchor copy', 'language-child' => 'parent child copy'],
            $prepared[0]->property('headline')?->jsonSerialize()
        );
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

    #[TestDox('carries the reduced and substituted values in the pre-prune forest, discarded subtree included')]
    public function testPrepareCarriesPreparedValuesInTheFullModePrePruneForest(): void
    {
        $root = StoredElementBuilder::create('section', 'root-id')
            ->withSlot('default', [
                StoredElementBuilder::create('text', 'target-id')
                    ->withConsumer('product', ContextType::Single)
                    ->build(),
                StoredElementBuilder::create('text', 'sibling-id')
                    ->withProperty('title', 'Product {{productId}}')
                    ->withProperty('headline', [Defaults::LANGUAGE_SYSTEM => 'anchor copy', 'language-child' => 'child copy'])
                    ->build(),
            ])
            ->build();
        $specification = new RenderingSpecification([], PlaceholderValues::from(['productId' => 'prod-1']), new Request(), 'target-id');

        $prepared = $this->preparer()->prepare(
            [$root],
            $specification,
            RenderingMode::FULL,
            $this->salesChannelContext(['language-child', Defaults::LANGUAGE_SYSTEM])
        );

        static::assertSame(['root-id', 'target-id'], $this->collectIds($prepared->tree));
        $discardedSibling = $prepared->prePruneForest[0]->slots['default'][1];
        static::assertSame('sibling-id', $discardedSibling->id);
        static::assertSame('Product prod-1', $discardedSibling->property('title')?->asString());
        static::assertSame('child copy', $discardedSibling->property('headline')?->asString());
    }

    #[TestDox('substitutes a declared token in a Literal loader-config value')]
    public function testPrepareResolvesLiteralConfigValues(): void
    {
        $config = new NavigationLoaderConfig('{{rootId}}');

        $element = StoredElementBuilder::create('navigation', 'root-id')
            ->withDataRequirement('navigationTree', 'test_literal', $config)
            ->build();

        $prepared = $this->prepare([$element], ['rootId' => 'cat-42']);

        $resolved = $prepared[0]->dataRequirements['navigationTree']->config;

        static::assertSame('cat-42', $resolved->jsonSerialize()['rootId']);
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

    /**
     * @param string|int|array<array-key, mixed> $value
     */
    #[DataProvider('nonMapTranslatableValueProvider')]
    #[TestDox('rejects a $_dataName on a translatable property as an internal fault')]
    public function testPrepareRejectsANonMapValueOnATranslatableProperty(string $key, string|int|array $value, string $expectedDeclaredType, string $expectedType): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty($key, $value)
            ->build();

        try {
            $this->prepare([$element], []);
            static::fail('Expected the non-map translatable value to be rejected.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::TRANSLATION_SHAPE_INVALID, $exception->getErrorCode());
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
            static::assertSame(
                \sprintf(
                    'Property "%s" of element "root-id" is declared %s and must hold a language map of its primitive, but holds %s.',
                    $key,
                    $expectedDeclaredType,
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
     * @return iterable<string, array{string, string|int|array<array-key, mixed>, string, string}>
     */
    public static function nonMapTranslatableValueProvider(): iterable
    {
        yield 'bare string' => ['headline', 'plain copy', 'string (translatable)', 'string'];
        yield 'bare integer on a translatable integer' => ['count', 7, 'integer (translatable)', 'int'];
        yield 'list' => ['headline', ['anchor copy'], 'string (translatable)', 'list'];
        // The outer shape is a map; the selected entry is what reaches the encoders, so it is judged too.
        yield 'map with a nested-map entry' => ['headline', [Defaults::LANGUAGE_SYSTEM => ['inner' => 'copy']], 'string (translatable)', 'a map whose selected entry is array'];
        // Declared-type-dependent: the same entry on the translatable integer `count` is selected above.
        yield 'map with an integer entry' => ['headline', [Defaults::LANGUAGE_SYSTEM => 7], 'string (translatable)', 'a map whose selected entry is int'];
        yield 'map with a string entry on a translatable integer' => ['count', [Defaults::LANGUAGE_SYSTEM => '7'], 'integer (translatable)', 'a map whose selected entry is string'];
        yield 'map with a null entry' => ['headline', [Defaults::LANGUAGE_SYSTEM => null], 'string (translatable)', 'a map whose selected entry is null'];

        // An empty map is the shape a writer reaches for to mean "no translations", and reduction refuses it:
        // absence of the key is that meaning. The raw `[]` arrives as the list variant, because
        // StoredValue::fromDecoded() wraps an array whose keys are a zero-based sequence as a list and an empty
        // array is such a sequence, so the reported type is `list` rather than get_debug_type([])'s `array`. The
        // benign contrast, a non-empty map that merely lacks the chain language, is pinned above: it collapses
        // to the null variant instead of throwing.
        yield 'empty map' => ['headline', [], 'string (translatable)', 'list'];
    }

    private function preparer(): StoredTreePreparer
    {
        $serializerLocator = static::createStub(ServiceLocator::class);
        $serializerLocator->method('has')->willReturnCallback(static fn (string $id): bool => $id === 'test_literal');
        $serializerLocator->method('get')->willReturnCallback(
            static fn (string $id): NavigationLoaderConfigSerializer => $id === 'test_literal'
                ? new NavigationLoaderConfigSerializer()
                : throw new \LogicException(\sprintf('Unexpected serializer id "%s"', $id))
        );

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('configSpecification')->willReturn(new LoaderConfigSpecification([
            new ConfigKeySpecification('rootId', ConfigKeyKind::Literal, 'string', required: false),
        ]));

        $loaderLocator = static::createStub(ServiceLocator::class);
        $loaderLocator->method('has')->willReturnCallback(static fn (string $id): bool => $id === 'test_literal');
        $loaderLocator->method('get')->willReturnCallback(
            static fn (string $id): AbstractContentDataLoader => $id === 'test_literal'
                ? $loader
                : throw new \LogicException(\sprintf('Unexpected loader id "%s"', $id))
        );

        return new StoredTreePreparer(
            $this->typeRegistry(),
            new VirtualRootWrapper(),
            new PartialRenderer(new ElementTreePruner(), new ContextDependencyAnalyzer(), new SubTreeExtractor()),
            new DataLoaderConfigSerializerProvider($serializerLocator),
            new DataLoaderProvider($loaderLocator),
        );
    }

    /**
     * Only `text` is registered, and it declares the keys the reduction cases read: one plain string, two
     * translatable strings differing in the required flag, and one translatable key per other primitive. Every
     * other key these fixtures store is undeclared, and `section` names no type at all, which is the shape the
     * virtual root is in too.
     */
    private function typeRegistry(): AbstractContentSystemElementTypeRegistry
    {
        return TestElementTypeRegistry::of([
            'text' => ContentSystemElementTypeSpecificationBuilder::create('text')
                ->primitive('title', 'string')
                ->primitive('headline', 'string', translatable: true)
                ->primitive('requiredHeadline', 'string', required: true, translatable: true)
                ->primitive('count', 'integer', translatable: true)
                ->primitive('visible', 'boolean', translatable: true)
                ->primitive('ratio', 'number', translatable: true)
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
