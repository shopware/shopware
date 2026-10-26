<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Inline;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\MappingRuntimeDiagnosticCollector;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingExpander;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingInterpolator;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingMetadata;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingToken;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingRuntimeResolver;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubPathStruct;

/**
 * The stored-tree rewrite that turns tokens into text before the mint reads a property.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(InlineMappingExpander::class)]
class InlineMappingExpanderTest extends TestCase
{
    private const COMPONENT = 'Sw:Content:Text';

    public function testExpandsATokenInAnInlineMappableProperty(): void
    {
        $element = $this->element(['text' => StoredValue::ofString('Buy the {{map:product.name}} today')]);

        $expanded = $this->expandPrepared($this->expander(), [$element], $this->ambient(), 'product');

        static::assertSame('Buy the Shirt today', $expanded[0]->property('text')?->asString());
    }

    /**
     * The flag is the gate, not the token's own presence. Expanding on the strength of a token alone would make
     * `inlineMappable` advisory.
     */
    #[TestDox('preserves literal mapping syntax in a property that did not declare inlineMappable')]
    public function testLeavesATokenInAPropertyThatDidNotOptIn(): void
    {
        $element = $this->element(['other' => StoredValue::ofString('{{map:product.name}}')]);

        $expanded = $this->expandPrepared($this->expander(), [$element], $this->ambient(), 'product');

        static::assertSame('{{map:product.name}}', $expanded[0]->property('other')?->asString());
    }

    #[TestDox('removes an unavailable token while preserving surrounding authored text')]
    public function testRemovesAnUncataloguedTokenAndPreservesSurroundingText(): void
    {
        $element = $this->element(['text' => StoredValue::ofString('Buy {{map:product.secret}} today')]);

        $expanded = $this->expandPrepared($this->expander(), [$element], $this->ambient(), 'product');

        static::assertSame('Buy  today', $expanded[0]->property('text')?->asString());
    }

    public function testRemovesATokenWhenTheCataloguedSourceHasNoValue(): void
    {
        $element = $this->element(['text' => StoredValue::ofString('Buy {{map:product.name}} today')]);

        $expanded = $this->expandPrepared($this->expander(), [$element], ['product' => new StubPathStruct()], 'product');

        static::assertSame('Buy  today', $expanded[0]->property('text')?->asString());
    }

    public function testKeepsTheTextPropertyWhenTheResolvedValueIsAnEmptyString(): void
    {
        $element = $this->element(['text' => StoredValue::ofString('Name: {{map:product.name}}')]);

        $expanded = $this->expandPrepared($this->expander(), [$element], ['product' => new StubPathStruct(name: '')], 'product');

        static::assertSame('Name: ', $expanded[0]->property('text')?->asString());
    }

    #[TestDox('removes an uncatalogued token independently of render surface')]
    public function testRemovesAnUncataloguedToken(): void
    {
        $element = $this->element(['text' => StoredValue::ofString('{{map:product.secret}}')]);

        $expanded = $this->expandPrepared($this->expander(), [$element], $this->ambient(), 'product');

        static::assertSame('', $expanded[0]->property('text')?->asString());
    }

    #[TestDox('removes a malformed dotless mapping token before rendering')]
    public function testRemovesAMalformedDotlessToken(): void
    {
        $element = $this->element(['text' => StoredValue::ofString('Buy {{map:product}} today')]);

        $expanded = $this->expandPrepared($this->expander(), [$element], $this->ambient(), 'product');

        static::assertSame('Buy  today', $expanded[0]->property('text')?->asString());
    }

    #[TestDox('expands a plugin token for a rootless section source')]
    public function testExpandsPluginTokenForRootlessSectionSource(): void
    {
        $candidate = new MappingCandidate(
            path: 'plugin:example.message',
            label: 'currency code',
            description: '',
            group: 'plugin',
            valueType: 'string',
            source: new MappingSourceReference('plugin', 'example', path: 'message'),
        );
        $candidates = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $candidates->method('forRootSource')->willReturn([$candidate->path => $candidate]);
        $candidates->method('resolveSource')->willReturn(MappingSourceResolution::resolved('EUR'));
        $mappingInspector = $this->mappingInspector($candidates);
        $expander = new InlineMappingExpander(
            $mappingInspector,
            new InlineMappingTokenParser(),
            new InlineMappingInterpolator(
                new MappingRuntimeResolver(
                    $candidates,
                    $mappingInspector,
                ),
            ),
        );
        $element = $this->element(['text' => StoredValue::ofString('Currency: {{map:plugin:example.message}}')]);

        $expanded = $this->expandPrepared($expander, [$element], [], 'header');

        static::assertSame('Currency: EUR', $expanded[0]->property('text')?->asString());
    }

    #[TestDox('removes an unknown inline token for a rootless section source')]
    public function testRemovesUnknownTokenForRootlessSectionSource(): void
    {
        $element = $this->element(['text' => StoredValue::ofString('{{map:plugin:example.unknown}}')]);

        $expanded = $this->expandPrepared($this->expander(), [$element], [], 'header');

        static::assertSame('', $expanded[0]->property('text')?->asString());
    }

    #[TestDox('removes inline tokens when the layout has no root source')]
    public function testRemovesTokensWithoutARootSource(): void
    {
        $candidates = static::createMock(AbstractContentSystemMappingCandidateRegistry::class);
        $candidates->expects($this->never())->method('forRootSource');
        $candidates->expects($this->never())->method('resolveSource');
        $element = $this->element(['text' => StoredValue::ofString('{{map:product.name}}')]);
        $forest = [$element];

        $expanded = $this->expandPrepared($this->expander($candidates), $forest, $this->ambient(), null);

        static::assertSame('', $expanded[0]->property('text')?->asString());
    }

    /**
     * Container strings are outside the inline mapping contract.
     */
    #[TestDox('preserves mapping-like text nested in a list or map property')]
    public function testPreservesTokensInsideContainerProperties(): void
    {
        $element = $this->element([
            'text' => StoredValue::ofMap([
                'nested' => StoredValue::ofList([StoredValue::ofString('{{map:product.name}}')]),
            ]),
        ]);

        $expanded = $this->expandPrepared($this->expander(), [$element], $this->ambient(), 'product');

        static::assertSame('{{map:product.name}}', $expanded[0]->property('text')?->asMap()['nested']->asList()[0]->asString());
    }

    #[TestDox('expands tokens in slot children too')]
    public function testExpandsNestedElements(): void
    {
        $child = $this->element(['text' => StoredValue::ofString('{{map:product.name}}')], id: 'child');
        $parent = new StoredElement(
            id: 'parent',
            component: self::COMPONENT,
            slots: ['content' => [$child]],
        );

        $expanded = $this->expandPrepared($this->expander(), [$parent], $this->ambient(), 'product');

        static::assertSame('Shirt', $expanded[0]->slots['content'][0]->property('text')?->asString());
    }

    #[TestDox('leaves a non-string property untouched')]
    public function testLeavesNonStringPropertiesUntouched(): void
    {
        $element = $this->element(['text' => StoredValue::ofInt(42)]);

        $expanded = $this->expandPrepared($this->expander(), [$element], $this->ambient(), 'product');

        static::assertSame(42, $expanded[0]->property('text')?->asInt());
    }

    /**
     * An unregistered component is reported as an intrinsic violation elsewhere; its authored text remains.
     */
    #[TestDox('preserves mapping-like text on an unregistered component')]
    public function testLeavesATokenAloneOnAnUnregisteredComponent(): void
    {
        $element = $this->element(['text' => StoredValue::ofString('{{map:product.name}}')], component: 'Sw:Unknown');

        $expanded = $this->expandPrepared($this->expander(), [$element], $this->ambient(), 'product');

        static::assertSame('{{map:product.name}}', $expanded[0]->property('text')?->asString());
    }

    public function testOversizedExpandedPropertyAbortsRendering(): void
    {
        $element = $this->element([
            'text' => StoredValue::ofString('<p>' . str_repeat('{{map:product.name}}', 17) . '</p>'),
            'other' => StoredValue::ofString('Unaffected'),
        ]);
        $diagnostics = new MappingRuntimeDiagnosticCollector();

        $this->expectExceptionObject(ContentSystemException::inlineMappingExpansionTooLarge(1048576, 'property'));

        $this->expandPrepared(
            $this->expander(),
            [$element],
            ['product' => new StubPathStruct(name: str_repeat('x', 65536))],
            'product',
            $diagnostics,
        );
    }

    public function testAllowsExactlyOneMiBIncludingSurroundingMarkup(): void
    {
        $element = $this->element(['text' => StoredValue::ofString('<p>{{map:product.name}}</p>')]);
        $source = str_repeat('x', 1048576 - 7);
        $diagnostics = new MappingRuntimeDiagnosticCollector();

        $expanded = $this->expandPrepared($this->expander(), [$element], ['product' => new StubPathStruct(name: $source)], 'product', $diagnostics);

        static::assertSame('<p>' . $source . '</p>', $expanded[0]->property('text')?->asString());
        static::assertSame([], $diagnostics->violations());
    }

    public function testCountsEscapingAgainstThePropertyLimit(): void
    {
        $element = $this->element(['text' => StoredValue::ofString('{{map:product.name}}')]);
        $diagnostics = new MappingRuntimeDiagnosticCollector();

        $this->expectExceptionObject(ContentSystemException::inlineMappingExpansionTooLarge(1048576, 'property'));

        $this->expandPrepared(
            $this->expander(),
            [$element],
            ['product' => new StubPathStruct(name: str_repeat('"', 174763))],
            'product',
            $diagnostics,
        );
    }

    public function testSharesTheRenderBudgetAcrossPropertiesRootsAndChildrenAndStopsResolving(): void
    {
        $candidate = new MappingCandidate('product.name', 'Name', '', 'basic', 'string');
        $candidates = static::createMock(AbstractContentSystemMappingCandidateRegistry::class);
        $candidates->method('forRootSource')->willReturn([$candidate->path => $candidate]);
        $candidates->expects($this->exactly(4))->method('resolveSource')
            ->willReturn(MappingSourceResolution::resolved(str_repeat('x', 1048576)));
        $child = $this->element(['text' => StoredValue::ofString('{{map:product.name}}')], id: 'child');
        $parent = $this->element([
            'text' => StoredValue::ofString('{{map:product.name}}'),
            'caption' => StoredValue::ofString('{{map:product.name}}'),
        ], id: 'parent')->withSlots(['content' => [$child]]);
        $forest = [$parent];
        foreach (['second-root', 'over-budget'] as $id) {
            $forest[] = $this->element(['text' => StoredValue::ofString('{{map:product.name}}')], id: $id);
        }
        $diagnostics = new MappingRuntimeDiagnosticCollector();

        $this->expectExceptionObject(ContentSystemException::inlineMappingExpansionTooLarge(4194304, 'render'));

        $this->expandPrepared($this->expander($candidates), $forest, [], 'product', $diagnostics);
    }

    public function testPropertyFailureStopsBeforeResolvingLaterProperties(): void
    {
        $candidate = new MappingCandidate('product.name', 'Name', '', 'basic', 'string');
        $candidates = static::createMock(AbstractContentSystemMappingCandidateRegistry::class);
        $candidates->method('forRootSource')->willReturn([$candidate->path => $candidate]);
        $candidates->expects($this->once())->method('resolveSource')
            ->willReturn(MappingSourceResolution::resolved(str_repeat('x', 1048576)));
        $forest = [];
        foreach (['first', 'second', 'third', 'fourth', 'fifth'] as $id) {
            $forest[] = $this->element(['text' => StoredValue::ofString('{{map:product.name}}{{map:product.name}}')], id: $id);
        }
        $diagnostics = new MappingRuntimeDiagnosticCollector();

        $this->expectExceptionObject(ContentSystemException::inlineMappingExpansionTooLarge(1048576, 'property'));

        $this->expandPrepared($this->expander($candidates), $forest, [], 'product', $diagnostics);
    }

    public function testStartsAFreshBudgetForEachRender(): void
    {
        $expander = $this->expander();
        $ambient = ['product' => new StubPathStruct(name: str_repeat('x', 1048576))];
        $forest = [];
        foreach (['first', 'second', 'third', 'fourth'] as $id) {
            $forest[] = $this->element(['text' => StoredValue::ofString('{{map:product.name}}')], id: $id);
        }
        $this->expandPrepared($expander, $forest, $ambient, 'product');
        $diagnostics = new MappingRuntimeDiagnosticCollector();

        $expanded = $this->expandPrepared($expander, [$forest[0]], $ambient, 'product', $diagnostics);

        static::assertSame(1048576, \strlen($expanded[0]->property('text')?->asString() ?? ''));
        static::assertSame([], $diagnostics->violations());
    }

    /**
     * @return array<string, mixed>
     */
    private function ambient(): array
    {
        return ['product' => new StubPathStruct(name: 'Shirt')];
    }

    /**
     * @param array<string, StoredValue> $properties
     */
    private function element(array $properties, string $id = 'el-1', string $component = self::COMPONENT): StoredElement
    {
        return new StoredElement(id: $id, component: $component, properties: $properties);
    }

    /**
     * @param list<StoredElement> $forest
     * @param array<string, mixed> $ambient
     *
     * @return list<StoredElement>
     */
    private function expandPrepared(InlineMappingExpander $expander, array $forest, array $ambient, ?string $rootSource, ?MappingRuntimeDiagnosticCollector $diagnostics = null): array
    {
        $tokens = [];
        $this->collectTokens($forest, $tokens);

        return $expander->expand($forest, $ambient, $rootSource, mappingDiagnostics: $diagnostics, inlineMappings: new InlineMappingMetadata($tokens));
    }

    /**
     * @param list<StoredElement> $forest
     * @param array<string, array<string, list<InlineMappingToken>>> $tokens
     */
    private function collectTokens(array $forest, array &$tokens): void
    {
        $parser = new InlineMappingTokenParser();

        foreach ($forest as $element) {
            foreach ($element->properties() as $key => $value) {
                if ($value->isString()) {
                    $parsed = $parser->parse($value->asString());
                    if ($parsed !== []) {
                        $tokens[$element->id][(string) $key] = $parsed;
                    }
                }
            }

            foreach ($element->slots as $children) {
                $this->collectTokens($children, $tokens);
            }
        }
    }

    private function expander(?AbstractContentSystemMappingCandidateRegistry $candidates = null): InlineMappingExpander
    {
        if ($candidates === null) {
            $candidates = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
            $candidates->method('forRootSource')->willReturnCallback(static fn (string $rootSource): array => $rootSource !== 'product' ? [] : [
                'product.name' => new MappingCandidate(
                    path: 'product.name',
                    label: 'a label',
                    description: 'a description',
                    group: 'basic',
                    valueType: 'string',
                ),
            ]);
            $candidates->method('resolveSource')->willReturnCallback(
                static function (string $rootSource, MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution {
                    $root = $context->rootValues[$source->id] ?? null;

                    $values = $root instanceof StubPathStruct ? $root->getVars() : [];

                    return \array_key_exists($source->path ?? '', $values)
                        ? MappingSourceResolution::resolved($values[$source->path ?? ''])
                        : MappingSourceResolution::noValue();
                }
            );
        }

        $mappingInspector = $this->mappingInspector($candidates);

        return new InlineMappingExpander(
            $mappingInspector,
            new InlineMappingTokenParser(),
            new InlineMappingInterpolator(
                new MappingRuntimeResolver(
                    $candidates,
                    $mappingInspector,
                ),
            ),
        );
    }

    /**
     * `text` opts in, `other` does not, which is what makes the flag check observable rather than incidental.
     */
    private function typeRegistry(): AbstractContentSystemElementTypeRegistry
    {
        $spec = ContentSystemElementTypeSpecificationBuilder::create(self::COMPONENT)
            ->primitive('text', 'string', inlineMappable: true)
            ->primitive('caption', 'string', inlineMappable: true)
            ->primitive('other', 'string')
            ->build();

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => $name === self::COMPONENT);
        $registry->method('get')->willReturnCallback(
            static fn (): ContentSystemElementTypeSpecification => $spec
        );

        return $registry;
    }

    private function mappingInspector(AbstractContentSystemMappingCandidateRegistry $candidates): StoredMappingInspector
    {
        return new StoredMappingInspector(
            $this->typeRegistry(),
            $candidates,
            new MappingTypeCompatibility(),
            new MappingConsumers(),
            new InlineMappingTokenParser(),
        );
    }
}
