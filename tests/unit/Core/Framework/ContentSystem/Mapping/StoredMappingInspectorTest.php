<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * The mapping admissibility rules, tested once here for the two surfaces that report them
 * (`Validation/StoredMappingValidator` on write, `Diagnostics/LayoutDiagnostics` on the editor routes).
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredMappingInspector::class)]
class StoredMappingInspectorTest extends TestCase
{
    private const CATEGORY_NAME_PATH = 'category.name';

    private const CATEGORY_MEDIA_PATH = 'category.media';

    public function testAdmitsAMappingOntoAMappablePropertyFromACataloguedPath(): void
    {
        $element = $this->elementMapping(self::CATEGORY_NAME_PATH, onto: 'text');

        static::assertSame([], $this->inspector($this->textElementType(mappable: true))->inspect([$element], 'category'));
    }

    public function testRejectsAMappingOntoAPropertyThatDidNotOptIn(): void
    {
        $element = $this->elementMapping(self::CATEGORY_NAME_PATH, onto: 'text');

        $problems = $this->inspector($this->textElementType(mappable: false))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame(ContentSystemException::PROPERTY_NOT_MAPPABLE, $problems[0]->exception->getErrorCode());
    }

    public function testRejectsAPathTheCatalogueDoesNotOffer(): void
    {
        $element = $this->elementMapping('category.internalNote', onto: 'text');

        $problems = $this->inspector($this->textElementType(mappable: true))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame(
            ContentSystemException::unknownMappingPath('category.internalNote', 'category')->getMessage(),
            $problems[0]->exception->getMessage()
        );
    }

    public function testRejectsACataloguedPathWhoseValueCannotFillTheProperty(): void
    {
        $element = $this->elementMapping(self::CATEGORY_MEDIA_PATH, onto: 'text');

        $problems = $this->inspector($this->textElementType(mappable: true))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame(
            ContentSystemException::mappingTypeMismatch('text', 'string', MediaEntity::class)->getMessage(),
            $problems[0]->exception->getMessage()
        );
    }

    public function testRejectsAStoredMappingWithDifferentContextCardinalityThanItsCandidate(): void
    {
        $element = $this->elementMapping(self::CATEGORY_NAME_PATH, onto: 'text', contextType: ContextType::Collection);

        $problems = $this->inspector($this->textElementType(mappable: true))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame(
            ContentSystemException::mappingContextTypeMismatch(self::CATEGORY_NAME_PATH, 'collection', 'single')->getMessage(),
            $problems[0]->exception->getMessage()
        );
    }

    /**
     * The discriminator that keeps these rules off pre-existing context wiring: a root-scoped consumer whose
     * alias names no declared property delivers onto an arbitrary key by design, and has done so since before
     * mapping existed.
     */
    public function testIgnoresARootScopedConsumerWhoseAliasNamesNoDeclaredProperty(): void
    {
        $element = new StoredElement(
            id: 'element-1',
            component: 'Sw:Content:Text',
            contextDefinitions: new ContextDefinitions(consumers: [
                'category.id' => new ContextConsumer(
                    type: ContextType::Single,
                    required: false,
                    propertyAlias: 'pageCategoryId',
                    scope: ConsumerScope::Root,
                ),
            ]),
        );

        static::assertSame([], $this->inspector($this->textElementType(mappable: false))->inspect([$element], 'category'));
    }

    /**
     * The regression this gate shipped with: `Mutation/ContextConsumerMirror` writes a root-scoped consumer
     * aliased onto a declared reference property for context it resolved against the root-ambient set, keyed
     * by the bare data-requirement name. `Sw:Product:Listing` receives the category page's listing exactly
     * that way, and judging it demanded `mappable: true` on `listing`, which rejected every listing page.
     */
    public function testIgnoresTheUndottedConsumerTheMutationLayerMirrorsForResolvedWiring(): void
    {
        $element = new StoredElement(
            id: 'element-1',
            component: 'Sw:Content:Text',
            contextDefinitions: new ContextDefinitions(consumers: [
                'productListing' => new ContextConsumer(
                    type: ContextType::Single,
                    required: false,
                    propertyAlias: 'text',
                    scope: ConsumerScope::Root,
                ),
            ]),
        );

        static::assertSame([], $this->inspector($this->textElementType(mappable: false))->inspect([$element], 'category'));
    }

    public function testIgnoresAParentScopedConsumer(): void
    {
        $element = new StoredElement(
            id: 'element-1',
            component: 'Sw:Content:Text',
            contextDefinitions: new ContextDefinitions(consumers: [
                'product' => new ContextConsumer(
                    type: ContextType::Single,
                    required: false,
                    propertyAlias: 'text',
                    scope: ConsumerScope::Parent,
                ),
            ]),
        );

        static::assertSame([], $this->inspector($this->textElementType(mappable: false))->inspect([$element], 'category'));
    }

    public function testReachesAMappingNestedInASlot(): void
    {
        $nested = $this->elementMapping(self::CATEGORY_NAME_PATH, onto: 'text', id: 'nested');
        $container = new StoredElement(
            id: 'container',
            component: 'Sw:Content:Text',
            slots: ['content' => [$nested]],
        );

        $problems = $this->inspector($this->textElementType(mappable: false))->inspect([$container], 'category');

        static::assertCount(1, $problems);
        static::assertSame('nested', $problems[0]->elementId);
    }

    /**
     * An unregistered component is already reported as an intrinsic well-formedness violation by both
     * surfaces, so reporting it again under a mapping code would double up on one defect.
     */
    public function testStaysSilentOnAnUnregisteredComponent(): void
    {
        $element = $this->elementMapping(self::CATEGORY_NAME_PATH, onto: 'text', component: 'Sw:Unknown');

        static::assertSame([], $this->inspector($this->textElementType(mappable: true))->inspect([$element], 'category'));
    }

    /**
     * The problem carries both halves because the two reporting surfaces need different ones: the property is
     * what each keys its entry on, and the path is what a message has to name to be actionable.
     */
    #[TestDox('names both the mapped property and the mapped path')]
    public function testNamesThePropertyAndThePath(): void
    {
        $element = $this->elementMapping(self::CATEGORY_NAME_PATH, onto: 'text');

        $problems = $this->inspector($this->textElementType(mappable: false))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame('element-1', $problems[0]->elementId);
        static::assertSame('text', $problems[0]->propertyKey);
        static::assertSame(self::CATEGORY_NAME_PATH, $problems[0]->sourceName);
    }

    #[TestDox('admits an inline token naming a catalogued string path in an inlineMappable property')]
    public function testAdmitsAnInlineTokenFromACataloguedPath(): void
    {
        $element = $this->elementWithText('Buy the {{map:category.name}} today');

        static::assertSame([], $this->inspector($this->textElementType(inlineMappable: true))->inspect([$element], 'category'));
    }

    public function testDiagnosesADotlessInlineTokenAsMalformed(): void
    {
        $element = $this->elementWithText('Buy the {{map:category}} today');

        $problems = $this->inspector($this->textElementType(inlineMappable: true))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame(
            ContentSystemException::MALFORMED_INLINE_MAPPING_TOKEN,
            $problems[0]->exception->getErrorCode(),
        );
        static::assertSame(
            ContentSystemException::malformedInlineMappingToken('category', 'text')->getMessage(),
            $problems[0]->exception->getMessage(),
        );
    }

    public function testDiagnosesADotlessInlineTokenWithoutABoundRootSource(): void
    {
        $element = $this->elementWithText('{{map:category}}');

        $problems = $this->inspector($this->textElementType(inlineMappable: true))->inspectInlineWithoutRootSource([$element]);

        static::assertCount(1, $problems);
        static::assertSame(
            ContentSystemException::MALFORMED_INLINE_MAPPING_TOKEN,
            $problems[0]->exception->getErrorCode(),
        );
    }

    #[TestDox('treats mapping-looking text as literal in a property that did not declare inlineMappable')]
    public function testIgnoresATokenInAPropertyThatDidNotOptIn(): void
    {
        $element = $this->elementWithText('Buy the {{map:category.name}} today');

        static::assertSame([], $this->inspector($this->textElementType(inlineMappable: false))->inspect([$element], 'category'));
    }

    #[TestDox('rejects an inline token naming a path the catalogue does not offer')]
    public function testRejectsAnInlineTokenWithAnUncataloguedPath(): void
    {
        $element = $this->elementWithText('A note: {{map:category.internalNote}}');

        $problems = $this->inspector($this->textElementType(inlineMappable: true))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame(ContentSystemException::UNKNOWN_INLINE_MAPPING_PATH, $problems[0]->exception->getErrorCode());
        static::assertSame('category.internalNote', $problems[0]->sourceName);
        // A bare string has no language, so its message names none.
        static::assertSame(
            ContentSystemException::unknownInlineMappingPath('category.internalNote', 'category')->getMessage(),
            $problems[0]->exception->getMessage(),
        );
    }

    public function testRejectsAnInlineCandidateReturnedUnderAnotherPathsKey(): void
    {
        $registry = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $registry->method('forRootSource')->willReturn([
            self::CATEGORY_NAME_PATH => new MappingCandidate('category.description', 'Description', '', 'basic', 'string'),
        ]);
        $inspector = new StoredMappingInspector(
            static::createStub(AbstractContentSystemElementTypeRegistry::class),
            $registry,
            new MappingTypeCompatibility(),
            new MappingConsumers(),
            new InlineMappingTokenParser(),
        );

        static::assertEquals(
            ContentSystemException::unknownInlineMappingPath(self::CATEGORY_NAME_PATH, 'category'),
            $inspector->admitInlinePath(self::CATEGORY_NAME_PATH, 'category'),
        );
    }

    #[TestDox('rejects an inline token whose catalogued value has no text form')]
    public function testRejectsAnInlineTokenWhoseValueIsNotStringifiable(): void
    {
        $element = $this->elementWithText('Look: {{map:category.media}}');

        $problems = $this->inspector($this->textElementType(inlineMappable: true))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame(
            ContentSystemException::INLINE_MAPPING_VALUE_NOT_STRINGIFIABLE,
            $problems[0]->exception->getErrorCode()
        );
    }

    /**
     * Escaping makes a value safe in text context only, so a token in attribute position is refused outright
     * rather than escaped and hoped for.
     */
    #[TestDox('rejects an inline token sitting inside an HTML tag rather than in text content')]
    public function testRejectsAnInlineTokenInsideMarkup(): void
    {
        $element = $this->elementWithText('<a href="/p/{{map:category.name}}">link</a>');

        $problems = $this->inspector($this->textElementType(inlineMappable: true))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame(ContentSystemException::INLINE_MAPPING_TOKEN_IN_MARKUP, $problems[0]->exception->getErrorCode());
    }

    public function testRejectsAttributeTokenAfterQuotedGreaterThan(): void
    {
        $element = $this->elementWithText('<a title="a > {{map:category.name}}">link</a>');

        $problems = $this->inspector($this->textElementType(inlineMappable: true))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame(ContentSystemException::INLINE_MAPPING_TOKEN_IN_MARKUP, $problems[0]->exception->getErrorCode());
    }

    #[TestDox('reports every bad token in one property rather than stopping at the first')]
    public function testReportsEveryBadTokenInOneProperty(): void
    {
        $element = $this->elementWithText('{{map:category.internalNote}} and {{map:category.otherNote}}');

        $problems = $this->inspector($this->textElementType(inlineMappable: true))->inspect([$element], 'category');

        static::assertCount(2, $problems);
        static::assertSame(
            ['category.internalNote', 'category.otherNote'],
            array_map(static fn ($problem): string => $problem->sourceName, $problems)
        );
    }

    /**
     * The gate's shape rule has to match what the render path expands, and the expander takes top-level string
     * properties only. A token inside a list would never be resolved, so reporting it would be a false alarm.
     */
    #[TestDox('ignores a token nested inside a container property, which the render path never expands')]
    public function testIgnoresATokenInsideAContainerProperty(): void
    {
        $element = new StoredElement(
            id: 'element-1',
            component: 'Sw:Content:Text',
            properties: ['text' => StoredValue::ofList([StoredValue::ofString('{{map:category.internalNote}}')])],
        );

        static::assertSame([], $this->inspector($this->textElementType(inlineMappable: true))->inspect([$element], 'category'));
    }

    #[TestDox('leaves an ordinary placeholder alone, because only the map: prefix marks a mapping')]
    public function testIgnoresAPlaceholderWithoutTheMapPrefix(): void
    {
        $element = $this->elementWithText('Hello {{productId}} and {{category.name}}');

        static::assertSame([], $this->inspector($this->textElementType(inlineMappable: false))->inspect([$element], 'category'));
    }

    /**
     * Language reduction may select any entry of a translatable property, so the gate judges each entry, including
     * one stored under a language that no longer exists: key existence is not a write input.
     */
    #[DataProvider('foreignLanguageEntryProvider')]
    #[TestDox('rejects a bad inline token in $_dataName of a translatable property')]
    public function testRejectsABadInlineTokenInAnyLanguageEntry(string $languageId): void
    {
        $element = $this->elementWithLanguageMap([
            Defaults::LANGUAGE_SYSTEM => 'Buy the {{map:category.name}} today',
            $languageId => 'Kauf {{map:category.internalNote}} heute',
        ]);

        $problems = $this->inspector($this->textElementType(inlineMappable: true, translatable: true))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame('element-1', $problems[0]->elementId);
        static::assertSame('text', $problems[0]->propertyKey);
        static::assertSame('category.internalNote', $problems[0]->sourceName);
        static::assertSame(ContentSystemException::UNKNOWN_INLINE_MAPPING_PATH, $problems[0]->exception->getErrorCode());
        static::assertSame(
            ContentSystemException::unknownInlineMappingPath('category.internalNote', 'category')->getMessage()
                . ' Found in the entry for language "' . $languageId . '".',
            $problems[0]->exception->getMessage(),
        );
    }

    #[TestDox('names the system language in the message of a fault in the system-language entry')]
    public function testNamesTheSystemLanguageForAFaultInItsEntry(): void
    {
        $element = $this->elementWithLanguageMap([Defaults::LANGUAGE_SYSTEM => 'Buy the {{map:category}} today']);

        $problems = $this->inspector($this->textElementType(inlineMappable: true, translatable: true))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertStringEndsWith(
            ' Found in the entry for language "' . Defaults::LANGUAGE_SYSTEM . '".',
            $problems[0]->exception->getMessage(),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignLanguageEntryProvider(): iterable
    {
        yield 'a non-system language entry' => ['language-german'];

        yield 'an entry under a deleted language' => ['language-deleted'];
    }

    #[TestDox('admits a translatable property whose every language entry holds a catalogued token')]
    public function testAdmitsAWellFormedInlineTokenInEveryLanguageEntry(): void
    {
        $element = $this->elementWithLanguageMap([
            Defaults::LANGUAGE_SYSTEM => 'Buy the {{map:category.name}} today',
            'language-german' => 'Kauf {{map:category.name}} heute',
        ]);

        static::assertSame([], $this->inspector($this->textElementType(inlineMappable: true, translatable: true))->inspect([$element], 'category'));
    }

    #[TestDox('reports a token in a non-system language entry as requiring a root source when none is bound')]
    public function testReportsATokenInALanguageEntryWithoutABoundRootSource(): void
    {
        $element = $this->elementWithLanguageMap([
            Defaults::LANGUAGE_SYSTEM => 'Welcome',
            'language-german' => 'Kauf {{map:category.name}} heute',
        ]);

        $problems = $this->inspector($this->textElementType(inlineMappable: true, translatable: true))->inspectInlineWithoutRootSource([$element]);

        static::assertCount(1, $problems);
        static::assertSame('text', $problems[0]->propertyKey);
        static::assertSame(ContentSystemException::INLINE_MAPPING_REQUIRES_ROOT_SOURCE, $problems[0]->exception->getErrorCode());
        static::assertSame(
            ContentSystemException::inlineMappingRequiresRootSource(self::CATEGORY_NAME_PATH, 'text')->getMessage()
                . ' Found in the entry for language "language-german".',
            $problems[0]->exception->getMessage(),
        );
    }

    #[TestDox('rejects a language entry over the authored-input cap')]
    public function testRejectsALanguageEntryOverTheInputCap(): void
    {
        $element = $this->elementWithLanguageMap([
            Defaults::LANGUAGE_SYSTEM => 'Welcome',
            'language-german' => '{{map:category.name}}' . str_repeat('a', InlineMappingTokenParser::MAX_INPUT_BYTES),
        ]);

        $this->expectExceptionObject(ContentSystemException::inlineMappingInputTooLarge(InlineMappingTokenParser::MAX_INPUT_BYTES));

        $this->inspector($this->textElementType(inlineMappable: true, translatable: true))->inspect([$element], 'category');
    }

    #[TestDox('applies the authored-input cap to each language entry, not to their sum')]
    public function testAdmitsLanguageEntriesEachUnderTheInputCapWhoseSumIsOverIt(): void
    {
        $text = '{{map:category.name}}' . str_repeat('a', intdiv(InlineMappingTokenParser::MAX_INPUT_BYTES, 2) + 1);
        $element = $this->elementWithLanguageMap([
            Defaults::LANGUAGE_SYSTEM => $text,
            'language-german' => $text,
        ]);

        static::assertSame([], $this->inspector($this->textElementType(inlineMappable: true, translatable: true))->inspect([$element], 'category'));
    }

    #[TestDox('treats mapping-looking text as literal in every entry of a translatable property that did not declare inlineMappable')]
    public function testIgnoresATokenInATranslatablePropertyThatDidNotOptIn(): void
    {
        $element = $this->elementWithLanguageMap([
            Defaults::LANGUAGE_SYSTEM => 'Buy the {{map:category.internalNote}} today',
            'language-german' => 'Kauf {{map:category}} heute',
        ]);

        static::assertSame([], $this->inspector($this->textElementType(translatable: true))->inspect([$element], 'category'));
    }

    private function elementWithText(string $text, string $id = 'element-1'): StoredElement
    {
        return new StoredElement(
            id: $id,
            component: 'Sw:Content:Text',
            properties: ['text' => StoredValue::ofString($text)],
        );
    }

    /**
     * @param array<string, string> $entries
     */
    private function elementWithLanguageMap(array $entries): StoredElement
    {
        return new StoredElement(
            id: 'element-1',
            component: 'Sw:Content:Text',
            properties: ['text' => StoredValue::ofMap(array_map(StoredValue::ofString(...), $entries))],
        );
    }

    private function elementMapping(
        string $path,
        string $onto,
        string $id = 'element-1',
        string $component = 'Sw:Content:Text',
        ContextType $contextType = ContextType::Single,
    ): StoredElement {
        return new StoredElement(
            id: $id,
            component: $component,
            contextDefinitions: new ContextDefinitions(consumers: [
                $onto => new ContextConsumer(
                    type: $contextType,
                    required: false,
                    scope: ConsumerScope::Root,
                    source: MappingSourceReference::fromRootPath($path),
                ),
            ]),
        );
    }

    private function textElementType(bool $mappable = false, bool $inlineMappable = false, bool $translatable = false): ContentSystemElementTypeSpecification
    {
        return ContentSystemElementTypeSpecificationBuilder::create('Sw:Content:Text')
            ->primitive('text', 'string', translatable: $translatable, mappable: $mappable, inlineMappable: $inlineMappable)
            ->build();
    }

    private function inspector(ContentSystemElementTypeSpecification $textType): StoredMappingInspector
    {
        $typeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $typeRegistry->method('has')->willReturnCallback(
            static fn (string $name): bool => $name === 'Sw:Content:Text'
        );
        $typeRegistry->method('get')->willReturn($textType);

        $candidateRegistry = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $candidateRegistry->method('forRootSource')->willReturn([
            self::CATEGORY_NAME_PATH => new MappingCandidate(
                path: self::CATEGORY_NAME_PATH,
                label: 'a label',
                description: 'a description',
                group: 'basic',
                valueType: 'string',
            ),
            self::CATEGORY_MEDIA_PATH => new MappingCandidate(
                path: self::CATEGORY_MEDIA_PATH,
                label: 'a label',
                description: 'a description',
                group: 'media',
                valueType: MediaEntity::class,
            ),
        ]);

        return new StoredMappingInspector(
            $typeRegistry,
            $candidateRegistry,
            new MappingTypeCompatibility(),
            new MappingConsumers(),
            new InlineMappingTokenParser(),
        );
    }
}
