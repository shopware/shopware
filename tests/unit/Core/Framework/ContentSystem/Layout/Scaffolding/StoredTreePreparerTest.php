<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationLoaderConfig;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationLoaderConfigSerializer;
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
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Output\ElementTreePruner;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\Output\SubTreeExtractor;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;

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
        $specs = [
            'text' => ContentSystemElementTypeSpecificationBuilder::create('text')
                ->primitive('text', 'string', inlineMappable: true)
                ->primitive('title', 'string')
                ->build(),
        ];
        $this->typeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $this->typeRegistry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $this->typeRegistry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);
    }

    public function testDoesNotReadTypeMetadataForPropertiesWithoutStrings(): void
    {
        $types = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $types->expects($this->never())->method('has');
        $types->expects($this->never())->method('get');
        $types->expects($this->never())->method('all');
        $this->typeRegistry = $types;
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('count', 0)
            ->withProperty('enabled', false)
            ->withProperty('settings', ['name' => '{{ordinary}}'])
            ->build();

        $result = $this->preparer()->prepare([$element], $this->specification([]), RenderingMode::FULL);

        static::assertSame(0, $result->tree[0]->property('count')?->jsonSerialize());
        static::assertFalse($result->tree[0]->property('enabled')?->jsonSerialize());
        static::assertSame(['name' => '{{ordinary}}'], $result->tree[0]->property('settings')?->jsonSerialize());
        static::assertSame([], $result->inlineMappings->tokens);
    }

    public function testReadsTypeMetadataOnceWhenSeveralPropertiesAreStrings(): void
    {
        $specification = ContentSystemElementTypeSpecificationBuilder::create('text')
            ->primitive('text', 'string', inlineMappable: true)
            ->primitive('title', 'string')
            ->build();
        $types = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $types->expects($this->once())->method('has')->with('text')->willReturn(true);
        $types->expects($this->once())->method('get')->with('text')->willReturn($specification);
        $this->typeRegistry = $types;
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'Hello {{name}}')
            ->withProperty('text', '{{map:product.name}}')
            ->build();

        $result = $this->preparer()->prepare([$element], $this->specification(['name' => 'World']), RenderingMode::FULL);

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

        $result = $this->preparer()->prepare([$element], $this->specification(['prefix' => $replacement]), RenderingMode::FULL);

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

        $result = $this->preparer()->prepare([$element], $this->specification(['query' => '{{map:product.name}}']), RenderingMode::FULL);
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

        $result = $this->preparer()->prepare([$element], $this->specification(['query' => '{{map:']), RenderingMode::FULL);
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
        $result = $this->preparer()->prepare([$parent], $this->specification(['name' => 'X']), RenderingMode::FULL);
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
        $result = $this->preparer()->prepare([$element], $this->specification(['prefix' => 'Longer prefix replacement']), RenderingMode::FULL);
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
            RenderingMode::SKELETON
        );

        // The target consumes context, so the prune keeps its ancestor for the data flow and drops the
        // sibling only; the pipeline's partial extract removes that ancestor after hydration.
        static::assertSame(['root-id', 'target-id'], $this->collectIds($prepared->tree));
        static::assertSame('target-id', $prepared->scaffolding->extractTargetId);
        // The sibling is what the prune drops. Wiring validation runs on this forest, so a defect in a
        // discarded subtree still has something to be judged against.
        static::assertSame(['root-id', 'target-id', 'sibling-id'], $this->collectIds($prepared->prePruneForest));
    }

    #[TestDox('carries the substituted values in the pre-prune forest, discarded subtree included')]
    public function testPrepareCarriesPreparedValuesInTheFullModePrePruneForest(): void
    {
        $root = StoredElementBuilder::create('section', 'root-id')
            ->withSlot('default', [
                StoredElementBuilder::create('text', 'target-id')
                    ->withConsumer('product', ContextType::Single)
                    ->build(),
                StoredElementBuilder::create('text', 'sibling-id')
                    ->withProperty('title', 'Product {{productId}}')
                    ->build(),
            ])
            ->build();
        $specification = new RenderingSpecification([], PlaceholderValues::from(['productId' => 'prod-1']), new Request(), 'target-id');

        $prepared = $this->preparer()->prepare([$root], $specification, RenderingMode::FULL);

        static::assertSame(['root-id', 'target-id'], $this->collectIds($prepared->tree));
        $discardedSibling = $prepared->prePruneForest[0]->slots['default'][1];
        static::assertSame('sibling-id', $discardedSibling->id);
        static::assertSame('Product prod-1', $discardedSibling->property('title')?->asString());
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

        $prepared = $this->preparer()->prepare([$root], $this->specification([]), RenderingMode::SKELETON);

        static::assertSame([$root], $prepared->tree);
        static::assertFalse($prepared->scaffolding->virtualRootSurvivedPrune);
    }

    #[TestDox('returns the tree unchanged in SKELETON mode')]
    public function testPrepareResolvesNothingInSkeletonMode(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'Product {{productId}}')
            ->build();

        $prepared = $this->preparer()->prepare(
            [$element],
            $this->specification(['productId' => 'prod-1']),
            RenderingMode::SKELETON
        );

        static::assertSame([$element], $prepared->tree);
    }

    #[TestDox('wraps the roots in a virtual root and carries the wrapped forest as the pre-prune forest for page-level data requirements')]
    public function testPrepareWrapsRootAndCarriesForestForPageLevelDataRequirements(): void
    {
        $root = StoredElementBuilder::create('section', 'root-id')->build();

        $prepared = $this->preparer()->prepare([$root], $this->pageContextSpecification(), RenderingMode::SKELETON);

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
        yield 'a lone unresolved token containing a colon stays verbatim' => ['{{map:product}}', []];
    }

    #[TestDox('collapses a lone unresolved token to null')]
    public function testPrepareCollapsesLoneUnresolvedTokenToNull(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', '{{categoryId}}')
            ->build();

        $prepared = $this->prepare([$element], ['productId' => 'prod-1']);

        static::assertTrue($prepared[0]->property('title')?->isNull());
    }

    /**
     * @param non-empty-string $input
     * @param array<string, string> $values
     */
    #[DataProvider('ordinaryPlaceholderCases')]
    public function testOrdinaryPlaceholderRulesApplyToPropertiesAndLiteralConfig(string $input, array $values, ?string $expected): void
    {
        $element = StoredElementBuilder::create('navigation', 'root-id')
            ->withProperty('title', $input)
            ->withDataRequirement('navigationTree', 'test_literal', new NavigationLoaderConfig($input))
            ->build();

        $prepared = $this->prepare([$element], $values)[0];

        $property = $prepared->property('title');
        static::assertNotNull($property);
        static::assertSame($expected, $property->jsonSerialize());
        $config = $prepared->dataRequirements['navigationTree']->config->jsonSerialize();
        if ($expected === null) {
            static::assertArrayNotHasKey('rootId', $config);
        } else {
            static::assertSame($expected, $config['rootId']);
        }
    }

    /**
     * @return iterable<string, array{non-empty-string, array<string, string>, string|null}>
     */
    public static function ordinaryPlaceholderCases(): iterable
    {
        yield 'missing ordinary key' => ['{{missing}}', [], null];
        yield 'missing colon key' => ['{{external:id}}', [], null];
        yield 'known ordinary key' => ['{{productId}}', ['productId' => 'prod-1'], 'prod-1'];
        yield 'known colon key' => ['{{external:id}}', ['external:id' => 'cat-1'], 'cat-1'];
        yield 'padded known key is not implicitly trimmed' => ['{{ productId }}', ['productId' => 'prod-1'], null];
        yield 'padded known colon key is not implicitly trimmed' => ['{{ external:id }}', ['external:id' => 'cat-1'], null];
        yield 'exact explicitly padded key still matches' => ['{{ productId }}', [' productId ' => 'exact-value'], 'exact-value'];
        yield 'embedded missing colon key stays literal' => ['Category {{external:id}}', [], 'Category {{external:id}}'];
        yield 'embedded padded key stays literal' => ['Product {{ productId }}', ['productId' => 'prod-1'], 'Product {{ productId }}'];
        yield 'reserved mapping stays literal' => ['{{map:product.name}}', ['map:product.name' => 'query value'], '{{map:product.name}}'];
        yield 'padded reserved mapping stays literal' => ['{{ map:product.name }}', [' map:product.name ' => 'query value'], '{{ map:product.name }}'];
        yield 'malformed reserved marker stays literal' => ['{{map:}}', ['map:' => 'query value'], '{{map:}}'];
        yield 'similar ordinary prefix is not reserved' => ['{{mapping:id}}', [], null];
        yield 'uppercase prefix is not reserved' => ['{{MAP:product.name}}', [], null];
        yield 'lone ordinary placeholder left after substitution collapses' => ['{{query}}', ['query' => '{{missing}}'], null];
        yield 'inserted mapping-looking text stays literal' => ['{{query}}', ['query' => '{{map:product.name}}'], '{{map:product.name}}'];
    }

    public function testInsertedLoneMappingTextDoesNotProduceAuthoredTokens(): void
    {
        $element = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('text', '{{query}}')
            ->build();

        $result = $this->preparer()->prepare([$element], $this->specification(['query' => '{{ map:product.name }}']), RenderingMode::FULL);

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

        $prepared = $this->preparer()->prepare([$root], $specification, RenderingMode::SKELETON);

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

        $prepared = $this->preparer()->prepare([$root], $this->targetedSpecification(''), RenderingMode::SKELETON);

        static::assertNull($prepared->scaffolding->extractTargetId);
        static::assertSame([$root], $prepared->tree);
    }

    #[TestDox('leaves an empty tree of roots empty')]
    public function testPrepareHandlesAnEmptyTreeOfRoots(): void
    {
        $prepared = $this->prepare([], ['productId' => 'prod-1']);

        static::assertSame([], $prepared);
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

        $result = $this->preparer()->prepare([$root], $this->targetedSpecification('target-id'), RenderingMode::FULL);

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
            new VirtualRootWrapper(),
            new PartialRenderer(new ElementTreePruner(), new ContextDependencyAnalyzer(), new SubTreeExtractor()),
            new DataLoaderConfigSerializerProvider($serializerLocator),
            new DataLoaderProvider($loaderLocator),
            new InlineMappingTokenParser(),
            $this->typeRegistry,
        );
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
     *
     * @return list<StoredElement>
     */
    private function prepare(array $tree, array $placeholderValues): array
    {
        return $this->preparer()->prepare($tree, $this->specification($placeholderValues), RenderingMode::FULL)->tree;
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
