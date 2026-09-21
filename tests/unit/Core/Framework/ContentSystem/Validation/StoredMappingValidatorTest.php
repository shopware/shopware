<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\AbstractContentPropertyProjection;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\ContentSystemPropertyProjectionRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Validation\StoredMappingValidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubUppercaseProjection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredMappingValidator::class)]
class StoredMappingValidatorTest extends TestCase
{
    private const CATEGORY_NAME_PATH = 'category.name';

    private const CATEGORY_MEDIA_PATH = 'category.media';

    private const CATEGORY_SHOUT_PATH = 'category.shoutedName';

    public function testAdmitsAMappingOntoAMappablePropertyFromACataloguedPath(): void
    {
        $element = $this->elementMapping(self::CATEGORY_NAME_PATH, onto: 'text');

        $violations = $this->validator($this->textElementType(mappable: true))->validate([$element], 'category');

        static::assertCount(0, $violations);
    }

    public function testRejectsAMappingOntoAPropertyThatDidNotOptIn(): void
    {
        $element = $this->elementMapping(self::CATEGORY_NAME_PATH, onto: 'text');

        $violations = $this->validator($this->textElementType(mappable: false))->validate([$element], 'category');

        static::assertCount(1, $violations);
        static::assertSame(
            ContentSystemException::propertyNotMappable('Sw:Content:Text', 'text')->getMessage(),
            $violations->get(0)->getMessage()
        );
    }

    public function testRejectsAPathTheCatalogueDoesNotOffer(): void
    {
        $element = $this->elementMapping('category.internalNote', onto: 'text');

        $violations = $this->validator($this->textElementType(mappable: true))->validate([$element], 'category');

        static::assertCount(1, $violations);
        static::assertSame(
            ContentSystemException::unknownMappingPath('category.internalNote', 'category')->getMessage(),
            $violations->get(0)->getMessage()
        );
    }

    public function testRejectsACataloguedPathWhoseValueCannotFillTheProperty(): void
    {
        $element = $this->elementMapping(self::CATEGORY_MEDIA_PATH, onto: 'text');

        $violations = $this->validator($this->textElementType(mappable: true))->validate([$element], 'category');

        static::assertCount(1, $violations);
        static::assertSame(
            ContentSystemException::mappingTypeMismatch('text', 'string', MediaEntity::class)->getMessage(),
            $violations->get(0)->getMessage()
        );
    }

    public function testAdmitsAMappingCarryingTheProjectionItsCandidateDeclares(): void
    {
        $element = $this->elementMapping(self::CATEGORY_SHOUT_PATH, onto: 'text', projection: StubUppercaseProjection::NAME);

        $violations = $this->validator($this->textElementType(mappable: true))->validate([$element], 'category');

        static::assertCount(0, $violations);
    }

    /**
     * The candidate's `valueType` describes the value AFTER its own projection, so a mapping that drops the
     * projection would be type-checked against a type nothing produces.
     */
    public function testRejectsAMappingThatDropsItsCandidatesProjection(): void
    {
        $element = $this->elementMapping(self::CATEGORY_SHOUT_PATH, onto: 'text');

        $violations = $this->validator($this->textElementType(mappable: true))->validate([$element], 'category');

        static::assertCount(1, $violations);
        static::assertSame(
            ContentSystemException::mappingProjectionMismatch(self::CATEGORY_SHOUT_PATH, null, StubUppercaseProjection::NAME)->getMessage(),
            $violations->get(0)->getMessage()
        );
    }

    public function testRejectsAProjectionOnACandidateThatDeclaresNone(): void
    {
        $element = $this->elementMapping(self::CATEGORY_NAME_PATH, onto: 'text', projection: StubUppercaseProjection::NAME);

        $violations = $this->validator($this->textElementType(mappable: true))->validate([$element], 'category');

        static::assertCount(1, $violations);
        static::assertSame(
            ContentSystemException::mappingProjectionMismatch(self::CATEGORY_NAME_PATH, StubUppercaseProjection::NAME, null)->getMessage(),
            $violations->get(0)->getMessage()
        );
    }

    /**
     * A provider bug rather than client input, reported as a violation so one broken provider fails only the
     * writes that use it.
     */
    public function testRejectsACandidateWhoseProjectionIsNotRegistered(): void
    {
        $element = $this->elementMapping(self::CATEGORY_SHOUT_PATH, onto: 'text', projection: StubUppercaseProjection::NAME);

        $violations = $this->validator($this->textElementType(mappable: true), projections: [])->validate([$element], 'category');

        static::assertCount(1, $violations);
        static::assertSame(
            ContentSystemException::unknownPropertyProjection(StubUppercaseProjection::NAME, self::CATEGORY_SHOUT_PATH)->getMessage(),
            $violations->get(0)->getMessage()
        );
    }

    /**
     * The discriminator that keeps this gate off pre-existing context wiring: a root-scoped consumer whose
     * alias names no declared property delivers onto an arbitrary key by design, and has done so since before
     * mapping existed.
     */
    public function testIgnoresARootScopedConsumerWhoseAliasNamesNoDeclaredProperty(): void
    {
        $element = $this->elementMapping('category.id', onto: 'pageCategoryId');

        $violations = $this->validator($this->textElementType(mappable: false))->validate([$element], 'category');

        static::assertCount(0, $violations);
    }

    /**
     * The regression this gate shipped with: `Mutation/ContextConsumerMirror` writes a root-scoped consumer
     * aliased onto a declared reference property for context it resolved against the root-ambient set, keyed
     * by the bare data-requirement name. `Sw:Product:Listing` receives the category page's listing exactly
     * that way, and judging it demanded `mappable: true` on `listing`, which rejected every listing page.
     */
    public function testIgnoresTheUndottedConsumerTheMutationLayerMirrorsForResolvedWiring(): void
    {
        $element = $this->elementMapping('productListing', onto: 'text');

        $violations = $this->validator($this->textElementType(mappable: false))->validate([$element], 'category');

        static::assertCount(0, $violations);
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

        $violations = $this->validator($this->textElementType(mappable: false))->validate([$element], 'category');

        static::assertCount(0, $violations);
    }

    public function testReachesAMappingNestedInASlot(): void
    {
        $nested = $this->elementMapping(self::CATEGORY_NAME_PATH, onto: 'text', id: 'nested');
        $container = new StoredElement(
            id: 'container',
            component: 'Sw:Content:Text',
            slots: ['content' => [$nested]],
        );

        $violations = $this->validator($this->textElementType(mappable: false))->validate([$container], 'category');

        static::assertCount(1, $violations);
        static::assertSame('/nested/text', $violations->get(0)->getPropertyPath());
    }

    /**
     * An unregistered component is already reported as an intrinsic well-formedness violation by the same
     * write, so reporting it again under a mapping code would double up on one defect.
     */
    public function testStaysSilentOnAnUnregisteredComponent(): void
    {
        $element = $this->elementMapping(self::CATEGORY_NAME_PATH, onto: 'text', component: 'Sw:Unknown');

        $violations = $this->validator($this->textElementType(mappable: true))->validate([$element], 'category');

        static::assertCount(0, $violations);
    }

    public function testPointsTheViolationAtTheMappedPropertyOfTheOffendingElement(): void
    {
        $element = $this->elementMapping(self::CATEGORY_NAME_PATH, onto: 'text');

        $violations = $this->validator($this->textElementType(mappable: false))->validate([$element], 'category');

        static::assertSame('/element-1/text', $violations->get(0)->getPropertyPath());
        static::assertSame(ContentSystemException::PROPERTY_NOT_MAPPABLE, $violations->get(0)->getCode());
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
                $path => new ContextConsumer(
                    type: ContextType::Single,
                    required: false,
                    propertyAlias: $onto,
                    scope: ConsumerScope::Root,
                    projection: $projection,
                ),
            ]),
        );
    }

    private function textElementType(bool $mappable): ContentSystemElementTypeSpecification
    {
        return ContentSystemElementTypeSpecificationBuilder::create('Sw:Content:Text')
            ->primitive('text', 'string', mappable: $mappable)
            ->build();
    }

    /**
     * @param list<AbstractContentPropertyProjection>|null $projections null registers the stub the catalogue
     *                                                                  below refers to; pass `[]` for an empty
     *                                                                  container
     */
    private function validator(ContentSystemElementTypeSpecification $textType, ?array $projections = null): StoredMappingValidator
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

        return new StoredMappingValidator(
            $typeRegistry,
            $candidateRegistry,
            new MappingTypeCompatibility(),
            new MappingConsumers(),
            new ContentSystemPropertyProjectionRegistry($projections ?? [new StubUppercaseProjection()]),
        );
    }
}
