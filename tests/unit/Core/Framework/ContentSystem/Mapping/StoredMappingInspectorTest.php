<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
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
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\AbstractContentPropertyProjection;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\ContentSystemPropertyProjectionRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubUppercaseProjection;

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

    private const CATEGORY_SHOUT_PATH = 'category.shoutedName';

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

    public function testAdmitsAMappingCarryingTheProjectionItsCandidateDeclares(): void
    {
        $element = $this->elementMapping(self::CATEGORY_SHOUT_PATH, onto: 'text', projection: StubUppercaseProjection::NAME);

        static::assertSame([], $this->inspector($this->textElementType(mappable: true))->inspect([$element], 'category'));
    }

    /**
     * The candidate's `valueType` describes the value AFTER its own projection, so a mapping that drops the
     * projection would be type-checked against a type nothing produces.
     */
    public function testRejectsAMappingThatDropsItsCandidatesProjection(): void
    {
        $element = $this->elementMapping(self::CATEGORY_SHOUT_PATH, onto: 'text');

        $problems = $this->inspector($this->textElementType(mappable: true))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame(
            ContentSystemException::mappingProjectionMismatch(self::CATEGORY_SHOUT_PATH, null, StubUppercaseProjection::NAME)->getMessage(),
            $problems[0]->exception->getMessage()
        );
    }

    public function testRejectsAProjectionOnACandidateThatDeclaresNone(): void
    {
        $element = $this->elementMapping(self::CATEGORY_NAME_PATH, onto: 'text', projection: StubUppercaseProjection::NAME);

        $problems = $this->inspector($this->textElementType(mappable: true))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame(
            ContentSystemException::mappingProjectionMismatch(self::CATEGORY_NAME_PATH, StubUppercaseProjection::NAME, null)->getMessage(),
            $problems[0]->exception->getMessage()
        );
    }

    /**
     * A provider bug rather than client input, reported as a problem so one broken provider fails only the
     * layouts that use it.
     */
    public function testRejectsACandidateWhoseProjectionIsNotRegistered(): void
    {
        $element = $this->elementMapping(self::CATEGORY_SHOUT_PATH, onto: 'text', projection: StubUppercaseProjection::NAME);

        $problems = $this->inspector($this->textElementType(mappable: true), projections: [])->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame(
            ContentSystemException::unknownPropertyProjection(StubUppercaseProjection::NAME, self::CATEGORY_SHOUT_PATH)->getMessage(),
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
        static::assertSame(self::CATEGORY_NAME_PATH, $problems[0]->sourcePath);
    }

    #[TestDox('admits an inline token naming a catalogued string path in an inlineMappable property')]
    public function testAdmitsAnInlineTokenFromACataloguedPath(): void
    {
        $element = $this->elementWithText('Buy the {{map:category.name}} today');

        static::assertSame([], $this->inspector($this->textElementType(inlineMappable: true))->inspect([$element], 'category'));
    }

    #[TestDox('rejects an inline token in a property that did not declare inlineMappable')]
    public function testRejectsAnInlineTokenInAPropertyThatDidNotOptIn(): void
    {
        $element = $this->elementWithText('Buy the {{map:category.name}} today');

        $problems = $this->inspector($this->textElementType(inlineMappable: false))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame(ContentSystemException::PROPERTY_NOT_INLINE_MAPPABLE, $problems[0]->exception->getErrorCode());
    }

    #[TestDox('rejects an inline token naming a path the catalogue does not offer')]
    public function testRejectsAnInlineTokenWithAnUncataloguedPath(): void
    {
        $element = $this->elementWithText('A note: {{map:category.internalNote}}');

        $problems = $this->inspector($this->textElementType(inlineMappable: true))->inspect([$element], 'category');

        static::assertCount(1, $problems);
        static::assertSame(ContentSystemException::UNKNOWN_INLINE_MAPPING_PATH, $problems[0]->exception->getErrorCode());
        static::assertSame('category.internalNote', $problems[0]->sourcePath);
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

    #[TestDox('reports every bad token in one property rather than stopping at the first')]
    public function testReportsEveryBadTokenInOneProperty(): void
    {
        $element = $this->elementWithText('{{map:category.internalNote}} and {{map:category.otherNote}}');

        $problems = $this->inspector($this->textElementType(inlineMappable: true))->inspect([$element], 'category');

        static::assertCount(2, $problems);
        static::assertSame(
            ['category.internalNote', 'category.otherNote'],
            array_map(static fn ($problem): string => $problem->sourcePath, $problems)
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

    #[TestDox('inspectInline reports the inline rules without the whole-field ones')]
    public function testInspectInlineReportsOnlyTheInlineRules(): void
    {
        $inlineOffender = $this->elementWithText('{{map:category.internalNote}}');
        $wholeFieldOffender = $this->elementMapping('category.internalNote', onto: 'text', id: 'element-2');

        $inspector = $this->inspector($this->textElementType(inlineMappable: true));
        $problems = $inspector->inspectInline([$inlineOffender, $wholeFieldOffender], 'category');

        static::assertCount(1, $problems);
        static::assertSame('element-1', $problems[0]->elementId);
        static::assertSame(ContentSystemException::UNKNOWN_INLINE_MAPPING_PATH, $problems[0]->exception->getErrorCode());
    }

    #[TestDox('leaves an ordinary placeholder alone, because only the map: prefix marks a mapping')]
    public function testIgnoresAPlaceholderWithoutTheMapPrefix(): void
    {
        $element = $this->elementWithText('Hello {{productId}} and {{category.name}}');

        static::assertSame([], $this->inspector($this->textElementType(inlineMappable: false))->inspect([$element], 'category'));
    }

    private function elementWithText(string $text, string $id = 'element-1'): StoredElement
    {
        return new StoredElement(
            id: $id,
            component: 'Sw:Content:Text',
            properties: ['text' => StoredValue::ofString($text)],
        );
    }

    private function elementMapping(
        string $path,
        string $onto,
        string $id = 'element-1',
        string $component = 'Sw:Content:Text',
        ?string $projection = null,
    ): StoredElement {
        return new StoredElement(
            id: $id,
            component: $component,
            contextDefinitions: new ContextDefinitions(consumers: [
                $onto => new ContextConsumer(
                    type: ContextType::Single,
                    required: false,
                    scope: ConsumerScope::Root,
                    projection: $projection,
                    sourcePath: $path,
                ),
            ]),
        );
    }

    private function textElementType(bool $mappable = false, bool $inlineMappable = false): ContentSystemElementTypeSpecification
    {
        return ContentSystemElementTypeSpecificationBuilder::create('Sw:Content:Text')
            ->primitive('text', 'string', mappable: $mappable, inlineMappable: $inlineMappable)
            ->build();
    }

    /**
     * @param list<AbstractContentPropertyProjection>|null $projections null registers the stub the catalogue
     *                                                                  below refers to; pass `[]` for an empty
     *                                                                  container
     */
    private function inspector(ContentSystemElementTypeSpecification $textType, ?array $projections = null): StoredMappingInspector
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
            self::CATEGORY_SHOUT_PATH => new MappingCandidate(
                path: self::CATEGORY_SHOUT_PATH,
                label: 'a label',
                description: 'a description',
                group: 'basic',
                valueType: 'string',
                projection: StubUppercaseProjection::NAME,
            ),
        ]);

        return new StoredMappingInspector(
            $typeRegistry,
            $candidateRegistry,
            new MappingTypeCompatibility(),
            new MappingConsumers(),
            new ContentSystemPropertyProjectionRegistry($projections ?? [new StubUppercaseProjection()]),
            new InlineMappingTokenParser(),
        );
    }
}
