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
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
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
    private AbstractContentSystemElementTypeRegistry $typeRegistry;

    protected function setUp(): void
    {
        $this->typeRegistry = $this->declaredTypes();
    }

    /**
     * Language reduction asks once whether the component is registered; an unregistered component leaves the
     * placeholder pass as the only further reader, so a second `has()` would be that pass reading metadata.
     */
    public function testDoesNotReadTypeMetadataForPropertiesWithoutStrings(): void
    {
        $types = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $types->expects($this->once())->method('has')->with('text')->willReturn(false);
        $types->expects($this->never())->method('get');
        $types->expects($this->never())->method('all');
        $this->typeRegistry = $types;
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('count', 0)
            ->withProperty('enabled', false)
            ->withProperty('settings', ['name' => '{{ordinary}}'])
            ->build();

        $result = $this->preparer()->prepare([$element], $this->specification([]), RenderingMode::FULL, $this->salesChannelContext());

        static::assertSame(0, $result->tree[0]->property('count')?->jsonSerialize());
        static::assertFalse($result->tree[0]->property('enabled')?->jsonSerialize());
        static::assertSame(['name' => '{{ordinary}}'], $result->tree[0]->property('settings')?->jsonSerialize());
        static::assertSame([], $result->inlineMappings->tokens);
    }

    /**
     * One read belongs to language reduction and one to the placeholder pass; a per-property read in the
     * placeholder pass would make it three.
     */
    public function testReadsTypeMetadataOncePerPassWhenSeveralPropertiesAreStrings(): void
    {
        $specification = ContentSystemElementTypeSpecificationBuilder::create('text')
            ->primitive('text', 'string', inlineMappable: true)
            ->primitive('title', 'string')
            ->build();
        $types = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $types->expects($this->exactly(2))->method('has')->with('text')->willReturn(true);
        $types->expects($this->exactly(2))->method('get')->with('text')->willReturn($specification);
        $this->typeRegistry = $types;
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'Hello {{name}}')
            ->withProperty('text', '{{map:product.name}}')
            ->build();

        $result = $this->preparer()->prepare([$element], $this->specification(['name' => 'World']), RenderingMode::FULL, $this->salesChannelContext());

        static::assertSame('Hello World', $result->tree[0]->property('title')?->asString());
        static::assertSame('product.name', $result->inlineMappings->tokensFor('root-id', 'text')[0]->path);
    }

    #[TestDox('substitutes a declared token in a string property')]
    public function testPrepareResolvesStringProperties(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'Product {{productId}}')
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame('Product prod-1', $prepared[0]->property('title')?->asString());
    }

    #[TestDox('preserves an inline mapping token for the render-time mapping pass')]
    public function testPreparePreservesAnInlineMappingToken(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('text', '{{map:product.name}}')
            ->build();

        $prepared = $this->prepare([$element], [
            'productId' => 'prod-1',
            'map:product.name' => 'query supplied text',
        ]);

        static::assertSame('{{map:product.name}}', $prepared[0]->property('text')?->asString());
    }

    #[TestDox('captures authored token spans after placeholder substitution shifts their byte offsets')]
    public function testPrepareCarriesAuthoredTokenSpans(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('text', 'A {{prefix}} {{map:product.name}}')
            ->build();

        $result = $this->preparer()->prepare(
            [$element],
            $this->specification(['prefix' => 'longer']),
            RenderingMode::FULL,
            $this->salesChannelContext(),
        );

        static::assertSame('A longer {{map:product.name}}', $result->tree[0]->property('text')?->asString());
        $token = $result->inlineMappings->tokensFor('root-id', 'text')[0];
        static::assertSame(9, $token->offset);
        static::assertSame('{{map:product.name}}', $token->match);
        static::assertSame('product.name', $token->path);
        static::assertFalse($token->insideMarkup);
        static::assertFalse($token->malformed);
    }

    public function testPrepareShiftsMultipleTokensPastALongerPlaceholderReplacement(): void
    {
        $replacement = 'A much longer replacement than the placeholder';
        $first = '{{map:product.name}}';
        $second = '{{map:product.description}}';
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('text', 'Before {{prefix}} ' . $first . ' middle ' . $second . ' after')
            ->build();

        $result = $this->preparer()->prepare([$element], $this->specification(['prefix' => $replacement]), RenderingMode::FULL, $this->salesChannelContext());

        $expected = 'Before ' . $replacement . ' ' . $first . ' middle ' . $second . ' after';
        static::assertSame($expected, $result->tree[0]->property('text')?->asString());
        $tokens = $result->inlineMappings->tokensFor('root-id', 'text');
        static::assertCount(2, $tokens);
        static::assertSame(['product.name', 'product.description'], array_column($tokens, 'path'));
        static::assertSame([$first, $second], array_column($tokens, 'match'));
        static::assertSame([
            \strlen('Before ' . $replacement . ' '),
            \strlen('Before ' . $replacement . ' ' . $first . ' middle '),
        ], array_column($tokens, 'offset'));
        foreach ($tokens as $token) {
            static::assertSame($token->match, substr($expected, $token->offset, \strlen($token->match)));
        }
    }

    #[TestDox('keeps authored mapping spans separate from placeholder-injected text')]
    public function testPrepareDoesNotTurnPlaceholderValuesIntoMappingInstructions(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('text', '{{map:product.name}} / {{query}}')
            ->build();

        $result = $this->preparer()->prepare([$element], $this->specification(['query' => '{{map:product.name}}']), RenderingMode::FULL, $this->salesChannelContext());
        $prepared = $result->tree;
        $tokens = $result->inlineMappings->tokensFor('root-id', 'text');
        static::assertCount(1, $tokens);
        static::assertSame(0, $tokens[0]->offset);
        static::assertSame('product.name', $tokens[0]->path);

        static::assertSame(
            '{{map:product.name}} / {{map:product.name}}',
            $prepared[0]->property('text')?->asString(),
        );
    }

    #[TestDox('does not parse mapping syntax assembled from placeholder text')]
    public function testPrepareDoesNotTurnPartialPlaceholderValuesIntoMappingInstructions(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('text', '{{query}}product.name}}')
            ->build();

        $result = $this->preparer()->prepare([$element], $this->specification(['query' => '{{map:']), RenderingMode::FULL, $this->salesChannelContext());
        $prepared = $result->tree;
        static::assertSame([], $result->inlineMappings->tokens);

        static::assertSame(
            '{{map:product.name}}',
            $prepared[0]->property('text')?->asString(),
        );
    }

    public function testCapturesInlineMetadataForNestedElements(): void
    {
        $child = StoredElementBuilder::create('text', 'nested')->withProperty('text', 'Hi {{name}} {{map:product.name}}')->build();
        $parent = StoredElementBuilder::create('section', 'parent')->withSlot('content', [$child])->build();
        $result = $this->preparer()->prepare([$parent], $this->specification(['name' => 'X']), RenderingMode::FULL, $this->salesChannelContext());
        $tokens = $result->inlineMappings->tokensFor('nested', 'text');
        static::assertCount(1, $tokens);
        static::assertSame('product.name', $tokens[0]->path);
        static::assertSame(5, $tokens[0]->offset);
        static::assertSame([], $result->inlineMappings->tokensFor('parent', 'text'));
    }

    public function testRebuiltTokenMetadataPreservesMarkupAndMalformedFlags(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('text', '{{prefix}} <a title="{{map:product.name}}">{{map:product}}</a>')->build();
        $result = $this->preparer()->prepare([$element], $this->specification(['prefix' => 'Longer prefix replacement']), RenderingMode::FULL, $this->salesChannelContext());
        $tokens = $result->inlineMappings->tokensFor('root-id', 'text');
        static::assertCount(2, $tokens);
        static::assertSame(['product.name', 'product'], array_column($tokens, 'path'));
        static::assertSame([true, false], array_column($tokens, 'insideMarkup'));
        static::assertSame([false, true], array_column($tokens, 'malformed'));
        $text = $result->tree[0]->property('text')?->asString();
        static::assertNotNull($text);
        foreach ($tokens as $token) {
            static::assertSame($token->match, substr($text, $token->offset, \strlen($token->match)));
        }
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

    #[TestDox('inserts a declared value that is itself a lone token into a Literal loader-config value verbatim')]
    public function testPrepareKeepsADeclaredLoneTokenLiteralConfigValue(): void
    {
        $element = StoredElementBuilder::create('navigation', 'root-id')
            ->withDataRequirement('navigationTree', 'test_literal', new NavigationLoaderConfig('{{rootId}}'))
            ->build();

        $prepared = $this->prepare([$element], ['rootId' => '{{categoryId}}']);

        static::assertSame(['rootId' => '{{categoryId}}'], $prepared[0]->dataRequirements['navigationTree']->config->jsonSerialize());
    }

    /**
     * Only a value that is entirely one `{{token}}` is unset. Each row embeds the undeclared token in a longer
     * string, so the key stays and its value is left as authored: dropping the `^` anchor fails the prefix row,
     * dropping the `$` anchor fails the suffix row, dropping the `D` modifier fails the trailing-newline row.
     *
     * @param non-empty-string $rootId
     */
    #[DataProvider('embeddedUnresolvedTokenProvider')]
    #[TestDox('keeps a Literal loader-config value verbatim when $_dataName')]
    public function testPrepareKeepsAnEmbeddedUnresolvedTokenLiteralConfigValue(string $rootId): void
    {
        $element = StoredElementBuilder::create('navigation', 'root-id')
            ->withDataRequirement('navigationTree', 'test_literal', new NavigationLoaderConfig($rootId))
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame(['rootId' => $rootId], $prepared[0]->dataRequirements['navigationTree']->config->jsonSerialize());
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function embeddedUnresolvedTokenProvider(): iterable
    {
        yield 'the token follows other text' => ['x {{rootId}}'];
        yield 'the token precedes other text' => ['{{rootId}} x'];
        yield 'the token is followed by a newline' => ["{{rootId}}\n"];
    }

    #[TestDox('removes a Literal loader-config key whose value is entirely one unresolved token')]
    public function testPrepareRemovesALoneUnresolvedTokenLiteralConfigKey(): void
    {
        $element = StoredElementBuilder::create('navigation', 'root-id')
            ->withDataRequirement('navigationTree', 'test_literal', new NavigationLoaderConfig('{{rootId}}', 3))
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame(['depth' => 3], $prepared[0]->dataRequirements['navigationTree']->config->jsonSerialize());
    }

    public function testPrepareDoesNotSubstituteReservedMappingNamesInLiteralLoaderConfig(): void
    {
        $element = StoredElementBuilder::create('navigation', 'root-id')
            ->withDataRequirement('navigationTree', 'test_literal', new NavigationLoaderConfig('root-{{productId}} {{map:product.name}}'))
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1', 'map:product.name' => 'query supplied text']);

        static::assertSame('root-prod-1 {{map:product.name}}', $prepared[0]->dataRequirements['navigationTree']->config->jsonSerialize()['rootId']);
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
     * Reduction applies no required rule of its own: the system-language requirement is the diagnostics
     * gate's business, so both sides of it leave the key out the same way.
     */
    #[DataProvider('unfilledTranslatablePropertyProvider')]
    #[TestDox('omits a $_dataName translatable property no chain language fills')]
    public function testPrepareOmitsAnUnfilledTranslatableProperty(string $key): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty($key, ['language-other' => 'other copy'])
            ->build();

        $prepared = $this->prepare([$element], []);

        // A key check, not a value check: a present null would be a different stored state.
        static::assertArrayNotHasKey($key, $prepared[0]->properties());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unfilledTranslatablePropertyProvider(): iterable
    {
        yield 'required' => ['requiredHeadline'];
        yield 'optional' => ['headline'];
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
        // A trailing newline makes the token embedded text, not the whole value.
        yield 'a lone token is followed by a newline' => ["{{categoryId}}\n", ['productId' => 'prod-1']];
        yield 'a lone unresolved token containing a colon stays verbatim' => ['{{map:product}}', []];
    }

    #[TestDox('keeps a token carried by a substituted value literal, even when that token is declared')]
    public function testPrepareDoesNotExpandATokenInsideASubstitutedValue(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'Product {{productId}}')
            ->build();

        $prepared = $this->prepare([$element], ['productId' => '{{categoryId}}', 'categoryId' => 'cat-1']);

        static::assertSame('Product {{categoryId}}', $prepared[0]->property('title')?->asString());
    }

    #[TestDox('keeps a property value that is entirely one unresolved token verbatim')]
    public function testPrepareKeepsALoneUnresolvedTokenPropertyVerbatim(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', '{{categoryId}}')
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertSame('{{categoryId}}', $prepared[0]->property('title')?->asString());
    }

    #[TestDox('keeps a declared value that is itself a lone token literal')]
    public function testPrepareKeepsADeclaredLoneTokenValueLiteral(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', '{{productId}}')
            ->build();

        $prepared = $this->prepare([$element], ['productId' => '{{categoryId}}']);

        static::assertSame('{{categoryId}}', $prepared[0]->property('title')?->asString());
    }

    /**
     * @param non-empty-string $input
     * @param array<string, string> $values
     */
    #[DataProvider('ordinaryPropertyPlaceholderCases')]
    #[TestDox('applies the ordinary placeholder rules to a property when $_dataName')]
    public function testOrdinaryPlaceholderRulesApplyToProperties(string $input, array $values, string $expected): void
    {
        $element = StoredElementBuilder::create('navigation', 'root-id')
            ->withProperty('title', $input)
            ->build();

        $prepared = $this->prepare([$element], $values);

        static::assertSame($expected, $prepared[0]->property('title')?->asString());
    }

    /**
     * @return iterable<string, array{non-empty-string, array<string, string>, string}>
     */
    public static function ordinaryPropertyPlaceholderCases(): iterable
    {
        yield 'missing ordinary key stays verbatim' => ['{{missing}}', [], '{{missing}}'];
        yield 'missing colon key stays verbatim' => ['{{external:id}}', [], '{{external:id}}'];
        yield 'known ordinary key' => ['{{productId}}', ['productId' => 'prod-1'], 'prod-1'];
        yield 'known colon key' => ['{{external:id}}', ['external:id' => 'cat-1'], 'cat-1'];
        yield 'padded known key is not implicitly trimmed' => ['{{ productId }}', ['productId' => 'prod-1'], '{{ productId }}'];
        yield 'padded known colon key is not implicitly trimmed' => ['{{ external:id }}', ['external:id' => 'cat-1'], '{{ external:id }}'];
        yield 'exact explicitly padded key still matches' => ['{{ productId }}', [' productId ' => 'exact-value'], 'exact-value'];
        yield 'embedded missing colon key stays literal' => ['Category {{external:id}}', [], 'Category {{external:id}}'];
        yield 'embedded padded key stays literal' => ['Product {{ productId }}', ['productId' => 'prod-1'], 'Product {{ productId }}'];
        yield 'reserved mapping stays literal' => ['{{map:product.name}}', ['map:product.name' => 'query value'], '{{map:product.name}}'];
        yield 'padded reserved mapping stays literal' => ['{{ map:product.name }}', [' map:product.name ' => 'query value'], '{{ map:product.name }}'];
        yield 'malformed reserved marker stays literal' => ['{{map:}}', ['map:' => 'query value'], '{{map:}}'];
        yield 'similar ordinary prefix is not reserved' => ['{{mapping:id}}', ['mapping:id' => 'mapped'], 'mapped'];
        yield 'uppercase prefix is not reserved' => ['{{MAP:product.name}}', ['MAP:product.name' => 'upper'], 'upper'];
        yield 'lone ordinary placeholder left after substitution stays verbatim' => ['{{query}}', ['query' => '{{missing}}'], '{{missing}}'];
        yield 'inserted mapping-looking text stays literal' => ['{{query}}', ['query' => '{{map:product.name}}'], '{{map:product.name}}'];
    }

    /**
     * @param non-empty-string $input
     * @param array<string, string> $values
     */
    #[DataProvider('keptLiteralConfigPlaceholderCases')]
    #[TestDox('keeps a Literal loader-config key and applies the ordinary placeholder rules when $_dataName')]
    public function testOrdinaryPlaceholderRulesApplyToLiteralConfig(string $input, array $values, string $expected): void
    {
        $element = StoredElementBuilder::create('navigation', 'root-id')
            ->withDataRequirement('navigationTree', 'test_literal', new NavigationLoaderConfig($input))
            ->build();

        $prepared = $this->prepare([$element], $values);

        static::assertSame(['rootId' => $expected], $prepared[0]->dataRequirements['navigationTree']->config->jsonSerialize());
    }

    /**
     * @return iterable<string, array{non-empty-string, array<string, string>, string}>
     */
    public static function keptLiteralConfigPlaceholderCases(): iterable
    {
        yield 'known ordinary key' => ['{{productId}}', ['productId' => 'prod-1'], 'prod-1'];
        yield 'known colon key' => ['{{external:id}}', ['external:id' => 'cat-1'], 'cat-1'];
        yield 'exact explicitly padded key still matches' => ['{{ productId }}', [' productId ' => 'exact-value'], 'exact-value'];
        yield 'embedded missing colon key stays literal' => ['Category {{external:id}}', [], 'Category {{external:id}}'];
        yield 'embedded padded key stays literal' => ['Product {{ productId }}', ['productId' => 'prod-1'], 'Product {{ productId }}'];
        yield 'reserved mapping stays literal' => ['{{map:product.name}}', ['map:product.name' => 'query value'], '{{map:product.name}}'];
        yield 'padded reserved mapping stays literal' => ['{{ map:product.name }}', [' map:product.name ' => 'query value'], '{{ map:product.name }}'];
        yield 'malformed reserved marker stays literal' => ['{{map:}}', ['map:' => 'query value'], '{{map:}}'];
        yield 'lone reserved mapping without a value is not removed' => ['{{map:product.name}}', [], '{{map:product.name}}'];
        yield 'lone reserved mapping with a request value is not removed' => ['{{map:product}}', ['map:product' => 'query value'], '{{map:product}}'];
        yield 'inserted mapping-looking text stays literal' => ['{{query}}', ['query' => '{{map:product.name}}'], '{{map:product.name}}'];
    }

    /**
     * Removal is judged on the authored input, so each row is a lone ordinary token whose exact key carries
     * no value.
     *
     * @param non-empty-string $input
     * @param array<string, string> $values
     */
    #[DataProvider('removedLiteralConfigPlaceholderCases')]
    #[TestDox('removes a Literal loader-config key when $_dataName')]
    public function testLiteralConfigRemovesALoneUnresolvedOrdinaryPlaceholder(string $input, array $values): void
    {
        $element = StoredElementBuilder::create('navigation', 'root-id')
            ->withDataRequirement('navigationTree', 'test_literal', new NavigationLoaderConfig($input, 3))
            ->build();

        $prepared = $this->prepare([$element], $values);

        static::assertSame(['depth' => 3], $prepared[0]->dataRequirements['navigationTree']->config->jsonSerialize());
    }

    /**
     * @return iterable<string, array{non-empty-string, array<string, string>}>
     */
    public static function removedLiteralConfigPlaceholderCases(): iterable
    {
        yield 'missing colon key' => ['{{external:id}}', []];
        yield 'padded known key is not implicitly trimmed' => ['{{ productId }}', ['productId' => 'prod-1']];
        yield 'padded known colon key is not implicitly trimmed' => ['{{ external:id }}', ['external:id' => 'cat-1']];
        yield 'similar ordinary prefix is not reserved' => ['{{mapping:id}}', []];
        yield 'uppercase prefix is not reserved' => ['{{MAP:product.name}}', []];
    }

    public function testInsertedLoneMappingTextDoesNotProduceAuthoredTokens(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('text', '{{query}}')
            ->build();

        $result = $this->preparer()->prepare([$element], $this->specification(['query' => '{{ map:product.name }}']), RenderingMode::FULL, $this->salesChannelContext());

        static::assertSame('{{ map:product.name }}', $result->tree[0]->property('text')?->asString());
        static::assertSame([], $result->inlineMappings->tokens);
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
        // benign contrast, a non-empty map that merely lacks the chain language, is pinned above: its key is
        // omitted instead of throwing.
        yield 'empty map' => ['headline', [], 'string (translatable)', 'list'];
    }

    #[DataProvider('literalPropertyProvider')]
    #[TestDox('preserves oversized literal text for $_dataName')]
    public function testPreparePreservesOversizedMappingLookingLiteralText(string $component, string $property): void
    {
        $prefix = str_repeat('x', InlineMappingTokenParser::MAX_INPUT_BYTES);
        $element = StoredElementBuilder::create($component, 'root-id')
            ->withProperty($property, $prefix . '{{productId}} {{map:product.name}}')
            ->build();

        $result = $this->preparer()->prepare(
            [$element],
            $this->specification(['productId' => 'prod-1', 'map:product.name' => 'query supplied text']),
            RenderingMode::FULL,
            $this->salesChannelContext(),
        );

        static::assertSame($prefix . 'prod-1 {{map:product.name}}', $result->tree[0]->property($property)?->asString());
        static::assertSame([], $result->inlineMappings->tokens);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function literalPropertyProvider(): iterable
    {
        yield 'declared property without inline mapping' => ['text', 'title'];
        yield 'undeclared property' => ['text', 'extra'];
        yield 'unregistered component' => ['unknown', 'text'];
    }

    public function testPrepareStillRejectsOversizedInlineMappingText(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('text', str_repeat('x', InlineMappingTokenParser::MAX_INPUT_BYTES) . '{{map:product.name}}')
            ->build();

        $this->expectExceptionObject(ContentSystemException::inlineMappingInputTooLarge(InlineMappingTokenParser::MAX_INPUT_BYTES));

        $this->prepare([$element], []);
    }

    public function testPrepareIgnoresOversizedLiteralMappingTextInAPrunedSibling(): void
    {
        $literal = str_repeat('x', InlineMappingTokenParser::MAX_INPUT_BYTES) . '{{map:product.name}}';
        $root = StoredElementBuilder::create('section', 'root-id')
            ->withSlot('default', [
                StoredElementBuilder::create('text', 'target-id')->withConsumer('product', ContextType::Single)->build(),
                StoredElementBuilder::create('text', 'sibling-id')->withProperty('title', $literal)->build(),
            ])
            ->build();

        $result = $this->preparer()->prepare([$root], $this->targetedSpecification('target-id'), RenderingMode::FULL, $this->salesChannelContext());

        static::assertSame(['root-id', 'target-id'], $this->collectIds($result->tree));
        static::assertSame($literal, $result->prePruneForest[0]->slots['default'][1]->property('title')?->asString());
        static::assertSame([], $result->inlineMappings->tokens);
    }

    #[DataProvider('reservedMappingPlaceholderProvider')]
    #[TestDox('keeps a reserved mapping name unsubstituted for a $_dataName')]
    public function testPrepareDoesNotSubstituteReservedMappingNamesInLiteralProperties(string $key): void
    {
        $literal = '{{' . $key . '}}';
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'Product {{productId}} ' . $literal)
            ->build();

        $result = $this->preparer()->prepare(
            [$element],
            $this->specification(['productId' => 'prod-1', $key => 'query supplied text']),
            RenderingMode::FULL,
            $this->salesChannelContext(),
        );

        static::assertSame('Product prod-1 ' . $literal, $result->tree[0]->property('title')?->asString());
        static::assertSame([], $result->inlineMappings->tokens);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function reservedMappingPlaceholderProvider(): iterable
    {
        yield 'canonical mapping token' => ['map:product.name'];
        yield 'mapping token with whitespace' => [' map:product.name '];
        yield 'mapping token with form feed' => ["\fmap:product.name"];
    }

    /**
     * The anchor entry carries its token at another offset, so a span captured on the anchor, or before
     * reduction, cannot match the selected string.
     */
    #[TestDox('captures an authored token span in the selected entry of a translatable property')]
    public function testPrepareCapturesATokenSpanInTheSelectedTranslationEntry(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('body', [
                Defaults::LANGUAGE_SYSTEM => '{{map:product.name}} anchor copy',
                'language-child' => 'Child {{prefix}} {{map:product.name}}',
            ])
            ->build();

        $result = $this->preparer()->prepare(
            [$element],
            $this->specification(['prefix' => 'longer']),
            RenderingMode::FULL,
            $this->salesChannelContext(['language-child', Defaults::LANGUAGE_SYSTEM]),
        );

        static::assertSame('Child longer {{map:product.name}}', $result->tree[0]->property('body')?->asString());
        $tokens = $result->inlineMappings->tokensFor('root-id', 'body');
        static::assertCount(1, $tokens);
        static::assertSame(\strlen('Child longer '), $tokens[0]->offset);
        static::assertSame('product.name', $tokens[0]->path);
    }

    #[TestDox('captures no token span from a translatable property in SKELETON mode')]
    public function testPrepareCapturesNoTokenSpanFromALanguageMapInSkeletonMode(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('body', [Defaults::LANGUAGE_SYSTEM => 'Copy {{map:product.name}}'])
            ->build();

        $result = $this->preparer()->prepare([$element], $this->specification([]), RenderingMode::SKELETON, $this->salesChannelContext());

        static::assertSame([], $result->inlineMappings->tokens);
    }

    #[TestDox('keeps a selected translation entry that is entirely one unresolved token verbatim')]
    public function testPrepareKeepsALoneUnresolvedTokenInTheSelectedTranslationVerbatim(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('headline', [Defaults::LANGUAGE_SYSTEM => 'anchor copy', 'language-child' => '{{categoryId}}'])
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1'], ['language-child', Defaults::LANGUAGE_SYSTEM]);

        static::assertSame('{{categoryId}}', $prepared[0]->property('headline')?->asString());
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
            $this->typeRegistry,
            new VirtualRootWrapper(),
            new PartialRenderer(new ElementTreePruner(), new ContextDependencyAnalyzer(), new SubTreeExtractor()),
            new DataLoaderConfigSerializerProvider($serializerLocator),
            new DataLoaderProvider($loaderLocator),
            new InlineMappingTokenParser(),
        );
    }

    /**
     * Only `text` is registered, and it declares the keys the cases read: one plain string, one inline-mappable
     * string, two translatable strings differing in the required flag, one translatable inline-mappable string,
     * and one translatable key per other primitive. Every other key these fixtures store is undeclared, and
     * `section` names no type at all, which is the shape the virtual root is in too.
     */
    private function declaredTypes(): AbstractContentSystemElementTypeRegistry
    {
        return TestElementTypeRegistry::of([
            'text' => ContentSystemElementTypeSpecificationBuilder::create('text')
                ->primitive('title', 'string')
                ->primitive('text', 'string', inlineMappable: true)
                ->primitive('body', 'string', translatable: true, inlineMappable: true)
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
