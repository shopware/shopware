<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Binding\ResolvedByLoaderBranch;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(BindingSpecificationCanonicalizer::class)]
class BindingSpecificationCanonicalizerTest extends TestCase
{
    #[TestDox('tier A: a bare property-reference string on an Entity-subclass property expands to the hardcoded entity wiring')]
    public function testTierAStringExpandsToCanonicalEntityWiring(): void
    {
        // An empty data-loader map: tier A no longer consults it, so this alone pins that no loader search runs.
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map([], []),
            $this->definitions(['media' => MediaEntity::class]),
        );

        $result = $canonicalizer->canonicalize($this->dto('image', ['media' => 'mediaId']), 'media-picker');

        static::assertIsArray($result->resolves);
        static::assertSame(
            ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            $result->resolves['media'],
        );
    }

    #[TestDox('tier A: a bare property-reference string on an EntityCollection-subclass property expands to the hardcoded entity_collection wiring')]
    public function testTierAStringOnEntityCollectionPropertyExpandsToCanonicalEntityCollectionWiring(): void
    {
        $type = $this->typeSpec('gallery', [
            'media' => $this->reference(MediaCollection::class, required: true),
        ]);

        $canonicalizer = $this->canonicalizer(
            ['gallery' => $type],
            $this->map([], []),
            ['media' => $this->collectionEntityDefinition('media', MediaCollection::class)],
        );

        $result = $canonicalizer->canonicalize($this->dto('gallery', ['media' => 'mediaIds']), 'binding');

        static::assertIsArray($result->resolves);
        static::assertSame(
            ['loader' => 'entity_collection', 'config' => ['entity' => 'media', 'property' => 'mediaIds']],
            $result->resolves['media'],
        );
    }

    #[TestDox('tier B: the single-key loader form auto-fills the required entity-name key from the reference FQCN')]
    public function testTierBEntityNameAutoFill(): void
    {
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
            $this->definitions(['media' => MediaEntity::class]),
        );

        $result = $canonicalizer->canonicalize($this->dto('image', ['media' => ['entity' => ['property' => 'mediaId']]]), 'binding');

        static::assertIsArray($result->resolves);
        static::assertSame(
            ['loader' => 'entity', 'config' => ['property' => 'mediaId', 'entity' => 'media']],
            $result->resolves['media'],
        );
    }

    #[TestDox('tier C: a map carrying a "loader" key passes through unchanged')]
    public function testLoaderKeyEntryPassesThroughAsTierC(): void
    {
        $canonical = ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']];

        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
        );

        $result = $canonicalizer->canonicalize($this->dto('image', ['media' => $canonical]), 'binding');

        static::assertIsArray($result->resolves);
        static::assertSame($canonical, $result->resolves['media']);
    }

    #[TestDox('a fully canonical tier C dto passes its resolves through unchanged and stamps the derived required flag onto its explicit input')]
    public function testFullyCanonicalDtoPassesResolvesThroughAndStampsInputs(): void
    {
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
        );

        $dto = new BindingSpecificationDto(
            'image',
            'From media library',
            ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']]],
            ['mediaId' => ['default' => 'seed']],
        );

        $result = $canonicalizer->canonicalize($dto, 'binding');

        static::assertSame($dto->type, $result->type);
        static::assertSame($dto->label, $result->label);
        static::assertSame($dto->resolves, $result->resolves);
        static::assertIsArray($result->inputs);
        static::assertSame(['mediaId' => ['default' => 'seed', 'required' => true]], $result->inputs);
    }

    #[TestDox('tier B: an explicitly authored entity-name key wins with no derivation, even when derivation would be ambiguous')]
    public function testTierBExplicitEntityNameWinsWithoutDerivation(): void
    {
        // Two registered entities both produce MediaEntity, so derivation would be ambiguous; the authored
        // "entity" key means derivation is never attempted, and expansion succeeds.
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
            $this->definitions(['media' => MediaEntity::class, 'media_clone' => MediaEntity::class]),
        );

        $result = $canonicalizer->canonicalize($this->dto('image', ['media' => ['entity' => ['entity' => 'media', 'property' => 'mediaId']]]), 'binding');

        static::assertIsArray($result->resolves);
        static::assertSame(
            ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            $result->resolves['media'],
        );
    }

    #[TestDox('FQCN derivation: matches the exact class only, not a subtype-producing definition')]
    public function testDerivationMatchesExactClassNotSubtype(): void
    {
        // "sales_channel_product" produces a subtype of the declared ProductEntity; only the exact "product"
        // definition matches, so derivation stays unambiguous.
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->typeSpec('image', ['item' => $this->reference(ProductEntity::class)])],
            $this->map(['entity' => [$this->capability(ProductEntity::class, ['entity' => 'product'])]], ['entity' => $this->entitySpec()]),
            $this->definitions(['product' => ProductEntity::class, 'sales_channel_product' => SalesChannelProductEntity::class]),
        );

        $result = $canonicalizer->canonicalize($this->dto('image', ['item' => ['entity' => ['property' => 'productId']]]), 'binding');

        static::assertIsArray($result->resolves);
        static::assertSame(
            ['loader' => 'entity', 'config' => ['property' => 'productId', 'entity' => 'product']],
            $result->resolves['item'],
        );
    }

    #[TestDox('FQCN derivation: uses the sales-channel produced class for an entity with a sales-channel definition')]
    public function testDerivationUsesSalesChannelProducedClass(): void
    {
        $salesChannelDefinition = static::createStub(SalesChannelProductDefinition::class);
        $salesChannelDefinition->method('getEntityClass')->willReturn(SalesChannelProductEntity::class);

        $salesChannelRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $salesChannelRegistry->method('getSalesChannelDefinitions')->willReturn(['product' => $salesChannelDefinition]);

        $canonicalizer = $this->canonicalizer(
            ['image' => $this->typeSpec('image', ['item' => $this->reference(SalesChannelProductEntity::class)])],
            $this->map(['entity' => [$this->capability(SalesChannelProductEntity::class, ['entity' => 'product'])]], ['entity' => $this->entitySpec()]),
            $this->definitions(['product' => ProductEntity::class]),
            $salesChannelRegistry,
        );

        $result = $canonicalizer->canonicalize($this->dto('image', ['item' => ['entity' => ['property' => 'productId']]]), 'binding');

        static::assertIsArray($result->resolves);
        static::assertSame(
            ['loader' => 'entity', 'config' => ['property' => 'productId', 'entity' => 'product']],
            $result->resolves['item'],
        );
    }

    #[TestDox('FQCN derivation: a mapping-entity definition producing the class is skipped, so it does not create a false ambiguity')]
    public function testDerivationSkipsMappingEntityDefinitions(): void
    {
        // Without the skip, the mapping definition (also producing MediaEntity) would make derivation ambiguous.
        $definitions = [
            'media' => $this->entityDefinition('media', MediaEntity::class),
            'media_tag' => $this->mappingDefinition('media_tag', MediaEntity::class),
        ];

        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
            $definitions,
        );

        $result = $canonicalizer->canonicalize($this->dto('image', ['media' => ['entity' => ['property' => 'mediaId']]]), 'binding');

        static::assertIsArray($result->resolves);
        static::assertSame(
            ['loader' => 'entity', 'config' => ['property' => 'mediaId', 'entity' => 'media']],
            $result->resolves['media'],
        );
    }

    #[TestDox('FQCN derivation: a definition whose entity class is the bare ArrayEntity is skipped, so it never satisfies the reference')]
    public function testDerivationSkipsArrayEntityDefinitions(): void
    {
        // The only definition producing the declared ArrayEntity FQCN is skipped (no addressable type), so
        // derivation finds nothing and fails rather than wiring an unaddressable entity.
        $canonicalizer = $this->canonicalizer(
            ['raw' => $this->typeSpec('raw', ['blob' => $this->reference(ArrayEntity::class)])],
            $this->map(['entity' => [$this->capability(ArrayEntity::class, ['entity' => 'raw_entity'])]], ['entity' => $this->entitySpec()]),
            ['raw_entity' => $this->entityDefinition('raw_entity', ArrayEntity::class)],
        );

        $exception = $this->expectCanonicalizationError($canonicalizer, $this->dto('raw', ['blob' => ['entity' => ['property' => 'blobId']]]), 'binding');

        static::assertSame(ContentSystemException::BINDING_SPECIFICATION_CANONICALIZATION_FAILED, $exception->getErrorCode());
        static::assertStringContainsString('no registered entity', $exception->getMessage());
    }

    #[TestDox('tier A: collection derivation uses the sales-channel produced collection class for an entity with a sales-channel definition')]
    public function testTierACollectionDerivationUsesSalesChannelCollectionClass(): void
    {
        $salesChannelDefinition = static::createStub(SalesChannelProductDefinition::class);
        $salesChannelDefinition->method('getCollectionClass')->willReturn(MediaCollection::class);

        $salesChannelRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $salesChannelRegistry->method('getSalesChannelDefinitions')->willReturn(['product' => $salesChannelDefinition]);

        $canonicalizer = $this->canonicalizer(
            ['gallery' => $this->typeSpec('gallery', ['media' => $this->reference(MediaCollection::class, required: true)])],
            $this->map([], []),
            ['product' => $this->collectionEntityDefinition('product', ProductCollection::class)],
            $salesChannelRegistry,
        );

        $result = $canonicalizer->canonicalize($this->dto('gallery', ['media' => 'mediaIds']), 'binding');

        static::assertIsArray($result->resolves);
        static::assertSame(
            ['loader' => 'entity_collection', 'config' => ['entity' => 'product', 'property' => 'mediaIds']],
            $result->resolves['media'],
        );
    }

    #[TestDox('tier A: collection derivation skips a definition whose bare collection class is the base EntityCollection, before its sales-channel class is consulted')]
    public function testTierACollectionDerivationSkipsBareEntityCollection(): void
    {
        // The plain definition produces the bare EntityCollection (unaddressable); the skip drops it before its
        // sales-channel collection class would satisfy the reference, so derivation finds nothing and fails rather
        // than wiring an unaddressable definition.
        $salesChannelDefinition = static::createStub(SalesChannelProductDefinition::class);
        $salesChannelDefinition->method('getCollectionClass')->willReturn(MediaCollection::class);

        $salesChannelRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $salesChannelRegistry->method('getSalesChannelDefinitions')->willReturn(['media' => $salesChannelDefinition]);

        $canonicalizer = $this->canonicalizer(
            ['gallery' => $this->typeSpec('gallery', ['media' => $this->reference(MediaCollection::class, required: true)])],
            $this->map([], []),
            ['media' => $this->collectionEntityDefinition('media', EntityCollection::class)],
            $salesChannelRegistry,
        );

        $exception = $this->expectCanonicalizationError($canonicalizer, $this->dto('gallery', ['media' => 'mediaIds']), 'binding');

        static::assertSame(ContentSystemException::BINDING_SPECIFICATION_CANONICALIZATION_FAILED, $exception->getErrorCode());
        static::assertStringContainsString('no registered entity', $exception->getMessage());
    }

    #[TestDox('tier B: an unauthored entity key on a collection-typed reference derives the collection entity name through the entity_collection loader')]
    public function testTierBCollectionEntityNameAutoFill(): void
    {
        $canonicalizer = $this->canonicalizer(
            ['gallery' => $this->typeSpec('gallery', ['media' => $this->reference(MediaCollection::class, required: true)])],
            $this->map(['entity_collection' => [$this->capability(MediaCollection::class, ['entity' => 'media'])]], ['entity_collection' => $this->entitySpec()]),
            ['media' => $this->collectionEntityDefinition('media', MediaCollection::class)],
        );

        $result = $canonicalizer->canonicalize($this->dto('gallery', ['media' => ['entity_collection' => ['property' => 'mediaIds']]]), 'binding');

        static::assertIsArray($result->resolves);
        static::assertSame(
            ['loader' => 'entity_collection', 'config' => ['property' => 'mediaIds', 'entity' => 'media']],
            $result->resolves['media'],
        );
    }

    /**
     * @param array<string, mixed> $resolves
     */
    #[DataProvider('synthesizesInputPerTierProvider')]
    #[TestDox('synthesis: $_dataName synthesizes a required input for its configured primitive property')]
    public function testSynthesizesInputPerTier(array $resolves, bool $needsDefinitions): void
    {
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
            $needsDefinitions ? $this->definitions(['media' => MediaEntity::class]) : [],
        );

        $result = $canonicalizer->canonicalize($this->dto('image', $resolves), 'binding');

        static::assertIsArray($result->inputs);
        static::assertSame(['mediaId' => ['required' => true]], $result->inputs);
    }

    #[TestDox('synthesis: an explicit inputs entry wins over synthesis, keeping its default and gaining the derived required flag with no duplicate')]
    public function testExplicitInputWinsOverSynthesis(): void
    {
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
            $this->definitions(['media' => MediaEntity::class]),
        );

        $dto = new BindingSpecificationDto('image', 'label', ['media' => 'mediaId'], ['mediaId' => ['default' => 'seed']]);

        $result = $canonicalizer->canonicalize($dto, 'binding');

        static::assertIsArray($result->inputs);
        static::assertSame(['mediaId' => ['default' => 'seed', 'required' => true]], $result->inputs);
    }

    #[TestDox('synthesis: a loader with two propertyReference keys synthesizes an input for each')]
    public function testMultiReferenceLoaderSynthesizesMultipleInputs(): void
    {
        $pairSpec = new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('primaryId', ConfigKeyKind::PropertyReference, 'string', required: true),
            new ConfigKeySpecification('secondaryId', ConfigKeyKind::PropertyReference, 'string', required: true),
        ]);

        $type = $this->typeSpec('combo', [
            'pair' => $this->reference(MediaEntity::class, required: true),
            'primaryId' => $this->primitive('string'),
            'secondaryId' => $this->primitive('string'),
        ]);

        $canonicalizer = $this->canonicalizer(
            ['combo' => $type],
            $this->map(['pair' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['pair' => $pairSpec]),
        );

        $dto = new BindingSpecificationDto('combo', 'label', ['pair' => ['loader' => 'pair', 'config' => ['entity' => 'media', 'primaryId' => 'primaryId', 'secondaryId' => 'secondaryId']]], []);

        $result = $canonicalizer->canonicalize($dto, 'binding');

        static::assertIsArray($result->inputs);
        static::assertSame(['primaryId' => ['required' => true], 'secondaryId' => ['required' => true]], $result->inputs);
    }

    #[TestDox('derived required: a required propertyReference key whose reference property is optional yields required:false')]
    public function testSynthesizedInputOptionalWhenReferenceIsOptional(): void
    {
        $type = $this->typeSpec('image', [
            'media' => $this->reference(MediaEntity::class, required: false),
            'mediaId' => $this->primitive('string'),
        ]);

        $canonicalizer = $this->canonicalizer(
            ['image' => $type],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
            $this->definitions(['media' => MediaEntity::class]),
        );

        $result = $canonicalizer->canonicalize($this->dto('image', ['media' => 'mediaId']), 'binding');

        static::assertIsArray($result->inputs);
        static::assertSame(['mediaId' => ['required' => false]], $result->inputs);
    }

    #[TestDox('derived required: a defaulted (optional) propertyReference key never makes its input required, even with a required reference (navigation shape)')]
    public function testDefaultedPropertyReferenceKeyNeverRequiresInput(): void
    {
        $navSpec = new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('activeProperty', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'active'),
        ]);

        $type = $this->typeSpec('nav', [
            'tree' => $this->reference(MediaEntity::class, required: true),
            'activeFlag' => $this->primitive('boolean'),
        ]);

        $canonicalizer = $this->canonicalizer(
            ['nav' => $type],
            $this->map(['navigation' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['navigation' => $navSpec]),
        );

        $dto = new BindingSpecificationDto('nav', 'label', ['tree' => ['loader' => 'navigation', 'config' => ['entity' => 'media', 'activeProperty' => 'activeFlag']]], []);

        $result = $canonicalizer->canonicalize($dto, 'binding');

        static::assertIsArray($result->inputs);
        static::assertSame(['activeFlag' => ['required' => false]], $result->inputs);
    }

    #[TestDox('derived required: an explicit input referenced by no resolves entry stays required:false')]
    public function testUnreferencedExplicitInputIsOptional(): void
    {
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
            $this->definitions(['media' => MediaEntity::class]),
        );

        $dto = new BindingSpecificationDto('image', 'label', ['media' => 'mediaId'], ['caption' => []]);

        $result = $canonicalizer->canonicalize($dto, 'binding');

        static::assertIsArray($result->inputs);
        static::assertSame(['mediaId' => ['required' => true], 'caption' => ['required' => false]], $result->inputs);
    }

    #[TestDox('resolves the declared type from the overlay when the registry does not carry it')]
    public function testResolvesTypeFromOverlayWhenRegistryLacksIt(): void
    {
        // The registry has no types (an app's own type at install time, app still inactive); the overlay supplies
        // it, so tier-B expansion and FQCN derivation run against the overlay spec.
        $canonicalizer = $this->canonicalizer(
            [],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
            $this->definitions(['media' => MediaEntity::class]),
        );

        $result = $canonicalizer->canonicalize(
            $this->dto('image', ['media' => ['entity' => ['property' => 'mediaId']]]),
            'binding',
            ['image' => $this->imageType()],
        );

        static::assertIsArray($result->resolves);
        static::assertSame(
            ['loader' => 'entity', 'config' => ['property' => 'mediaId', 'entity' => 'media']],
            $result->resolves['media'],
        );
    }

    #[TestDox('prefers the overlay type over a registered type of the same name')]
    public function testOverlayTakesPrecedenceOverRegistry(): void
    {
        // The registry carries an "image" without a "media" reference property; the overlay carries the real one.
        // Overlay-first means tier A resolves against the overlay, so the shorthand expands instead of failing.
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->typeSpec('image', [])],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
            $this->definitions(['media' => MediaEntity::class]),
        );

        $result = $canonicalizer->canonicalize(
            $this->dto('image', ['media' => 'mediaId']),
            'binding',
            ['image' => $this->imageType()],
        );

        static::assertIsArray($result->resolves);
        static::assertSame(
            ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            $result->resolves['media'],
        );
    }

    #[TestDox('synthesis: a tier-C entry naming an unregistered loader source synthesizes nothing and does not throw')]
    public function testUnregisteredLoaderSourceInTierCSynthesizesNothing(): void
    {
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
        );

        $dto = new BindingSpecificationDto('image', 'label', ['media' => ['loader' => 'ghost', 'config' => ['entity' => 'media', 'property' => 'mediaId']]], []);

        $result = $canonicalizer->canonicalize($dto, 'binding');

        static::assertIsArray($result->inputs);
        static::assertSame([], $result->inputs);
    }

    #[DataProvider('passesThroughScalarFacetProvider')]
    #[TestDox('a scalar resolves or inputs facet passes through unchanged, left for WellFormed to reject downstream')]
    public function testScalarFacetPassesThroughUnchanged(mixed $resolves, mixed $inputs, mixed $expectedResolves, mixed $expectedInputs): void
    {
        $canonicalizer = $this->canonicalizer(['image' => $this->imageType()], $this->map([], []));

        $result = $canonicalizer->canonicalize(new BindingSpecificationDto('image', 'label', $resolves, $inputs), 'binding');

        static::assertSame($expectedResolves, $result->resolves);
        static::assertSame($expectedInputs, $result->inputs);
    }

    #[TestDox('tier A: a bare string on a reference property whose FQCN is neither an Entity nor an EntityCollection subclass is a canonicalization error naming the explicit form')]
    public function testTierAOnNeitherEntityNorEntityCollectionFqcnThrows(): void
    {
        // Struct is a reference FQCN by declaration (non-primitive), but subclasses neither base class, so
        // no built-in resolvedBy loader applies.
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->typeSpec('image', ['item' => $this->reference(Struct::class)])],
            $this->map([], []),
        );

        $exception = $this->expectCanonicalizationError($canonicalizer, $this->dto('image', ['item' => 'someId']), 'binding');

        static::assertSame(ContentSystemException::BINDING_SPECIFICATION_CANONICALIZATION_FAILED, $exception->getErrorCode());
        static::assertStringContainsString('neither an Entity nor an EntityCollection subclass', $exception->getMessage());
        static::assertStringContainsString('tier B', $exception->getMessage());
        static::assertStringContainsString('tier C', $exception->getMessage());
    }

    #[TestDox('tier A: a string on a property that is not a declared reference is a canonicalization error')]
    public function testTierAOnNonReferencePropertyThrows(): void
    {
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->typeSpec('image', ['mediaId' => $this->primitive('string')])],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
        );

        $exception = $this->expectCanonicalizationError($canonicalizer, $this->dto('image', ['mediaId' => 'mediaId']), 'binding');

        static::assertSame(ContentSystemException::BINDING_SPECIFICATION_CANONICALIZATION_FAILED, $exception->getErrorCode());
        static::assertStringContainsString('declared reference property', $exception->getMessage());
    }

    #[TestDox('tier C: a "loader" key mixed with a registered loader-source key is a canonicalization error')]
    public function testLoaderKeyMixedWithLoaderSourceKeyThrows(): void
    {
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map(
                ['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]],
                ['entity' => $this->entitySpec()],
            ),
        );

        $entry = ['loader' => 'entity', 'config' => [], 'entity' => ['property' => 'mediaId']];

        $exception = $this->expectCanonicalizationError($canonicalizer, $this->dto('image', ['media' => $entry]), 'binding');

        static::assertSame(ContentSystemException::BINDING_SPECIFICATION_CANONICALIZATION_FAILED, $exception->getErrorCode());
        static::assertStringContainsString('mixes', $exception->getMessage());
        static::assertStringContainsString('entity', $exception->getMessage());
    }

    /**
     * @param array<string, mixed> $resolves
     */
    #[DataProvider('rejectsUnrecognizedShapeProvider')]
    #[TestDox('an entry that matches none of the three accepted shapes is a canonicalization error')]
    public function testUnrecognizedEntryShapeThrows(array $resolves): void
    {
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
        );

        $exception = $this->expectCanonicalizationError($canonicalizer, $this->dto('image', $resolves), 'binding');

        static::assertSame(ContentSystemException::BINDING_SPECIFICATION_CANONICALIZATION_FAILED, $exception->getErrorCode());
        static::assertStringContainsString('accepted shapes', $exception->getMessage());
    }

    #[DataProvider('rejectsUnknownTypeProvider')]
    #[TestDox('an unregistered, blank, or non-string type is a bindingSpecificationUnknownType error naming what the author wrote, before any resolves work')]
    public function testUnknownTypeThrows(mixed $type, string $expectedTypeInMessage): void
    {
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
        );

        $exception = $this->expectCanonicalizationError($canonicalizer, new BindingSpecificationDto($type, 'label', ['media' => 'mediaId'], []), 'binding');

        static::assertSame(ContentSystemException::BINDING_SPECIFICATION_UNKNOWN_TYPE, $exception->getErrorCode());
        static::assertStringContainsString('binding', $exception->getMessage());
        static::assertStringContainsString(\sprintf('element type "%s"', $expectedTypeInMessage), $exception->getMessage());
    }

    #[TestDox('a fully canonical dto whose type is unregistered still throws unknown-type')]
    public function testCanonicalDtoWithUnregisteredTypeStillThrowsUnknownType(): void
    {
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
        );

        $dto = new BindingSpecificationDto('Sw:Not:Registered', 'label', ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']]], []);

        $exception = $this->expectCanonicalizationError($canonicalizer, $dto, 'binding');

        static::assertSame(ContentSystemException::BINDING_SPECIFICATION_UNKNOWN_TYPE, $exception->getErrorCode());
    }

    #[TestDox('tier B: a config key the loader does not declare is a canonicalization error naming the key and the declared keys')]
    public function testTierBUnknownConfigKeyThrows(): void
    {
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
        );

        $exception = $this->expectCanonicalizationError($canonicalizer, $this->dto('image', ['media' => ['entity' => ['bogus' => 'x', 'property' => 'mediaId']]]), 'binding');

        static::assertSame(ContentSystemException::BINDING_SPECIFICATION_CANONICALIZATION_FAILED, $exception->getErrorCode());
        static::assertStringContainsString('bogus', $exception->getMessage());
        static::assertStringContainsString('property', $exception->getMessage());
    }

    /**
     * @param array<string, class-string> $producers
     */
    #[DataProvider('throwsTierADerivationFailureProvider')]
    #[TestDox('FQCN derivation through tier A: $_dataName is a canonicalization error naming the explicit-entityName fix')]
    public function testTierADerivationFailureThrows(ResolvedByLoaderBranch $branch, string $referenceFqcn, string $tierAValue, array $producers, string $expectedSubstring): void
    {
        // An empty data-loader map: tier A no longer consults it, so this alone pins that no loader search runs; the
        // reference FQCN's branch alone selects the entity vs. collection walk.
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->typeSpec('image', ['ref' => $this->reference($referenceFqcn, required: true)])],
            $this->map([], []),
            $this->branchDefinitions($branch, $producers),
        );

        $exception = $this->expectCanonicalizationError($canonicalizer, $this->dto('image', ['ref' => $tierAValue]), 'binding');

        static::assertSame(ContentSystemException::BINDING_SPECIFICATION_CANONICALIZATION_FAILED, $exception->getErrorCode());
        static::assertStringContainsString($expectedSubstring, $exception->getMessage());
        static::assertStringContainsString($referenceFqcn, $exception->getMessage());
    }

    /**
     * @param array<string, class-string> $producers
     */
    #[DataProvider('throwsTierBDerivationFailureProvider')]
    #[TestDox('rejects a tier-B single-key loader form whose $_dataName entity-name derivation fails')]
    public function testTierBDerivationFailureThrows(ResolvedByLoaderBranch $branch, string $referenceFqcn, string $property, array $producers, string $expectedSubstring): void
    {
        $loaderSource = match ($branch) {
            ResolvedByLoaderBranch::Entity => 'entity',
            ResolvedByLoaderBranch::EntityCollection => 'entity_collection',
        };

        $canonicalizer = $this->canonicalizer(
            ['element' => $this->typeSpec('element', ['media' => $this->reference($referenceFqcn, required: true)])],
            $this->map([], [$loaderSource => $this->entitySpec()]),
            $this->branchDefinitions($branch, $producers),
        );

        $exception = $this->expectCanonicalizationError($canonicalizer, $this->dto('element', ['media' => [$loaderSource => ['property' => $property]]]), 'binding');

        static::assertSame(ContentSystemException::BINDING_SPECIFICATION_CANONICALIZATION_FAILED, $exception->getErrorCode());
        static::assertStringContainsString($expectedSubstring, $exception->getMessage());
    }

    #[TestDox('tier B: an unauthored entity key on a reference whose FQCN is neither an Entity nor an EntityCollection subclass throws, naming the explicit-entityName fix')]
    public function testTierBNullBranchReferenceThrows(): void
    {
        // Struct is a declared reference FQCN (non-primitive) but subclasses neither base class, so no branch derives
        // its entity name; the fill throws directly instead of falling into a walk that cannot succeed.
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->typeSpec('image', ['item' => $this->reference(Struct::class, required: true)])],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
        );

        $exception = $this->expectCanonicalizationError($canonicalizer, $this->dto('image', ['item' => ['entity' => ['property' => 'itemId']]]), 'binding');

        static::assertSame(ContentSystemException::BINDING_SPECIFICATION_CANONICALIZATION_FAILED, $exception->getErrorCode());
        static::assertStringContainsString('neither an Entity nor an EntityCollection subclass', $exception->getMessage());
        static::assertStringContainsString('author the entity name explicitly', $exception->getMessage());
    }

    #[TestDox('authoring "required" inside an inputs entry is a canonicalization error, the flag being derived')]
    public function testAuthoredRequiredIsCanonicalizationError(): void
    {
        $canonicalizer = $this->canonicalizer(
            ['image' => $this->imageType()],
            $this->map(['entity' => [$this->capability(MediaEntity::class, ['entity' => 'media'])]], ['entity' => $this->entitySpec()]),
            $this->definitions(['media' => MediaEntity::class]),
        );

        $dto = new BindingSpecificationDto('image', 'label', ['media' => 'mediaId'], ['mediaId' => ['required' => true]]);

        $exception = $this->expectCanonicalizationError($canonicalizer, $dto, 'binding');

        static::assertSame(ContentSystemException::BINDING_SPECIFICATION_CANONICALIZATION_FAILED, $exception->getErrorCode());
        static::assertStringContainsString('required', $exception->getMessage());
        static::assertStringContainsString('derived', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function rejectsUnrecognizedShapeProvider(): iterable
    {
        yield 'integer value' => [['media' => 123]];
        yield 'multi-key map without a loader key' => [['media' => ['a' => 1, 'b' => 2]]];
        yield 'single key that is not a registered loader source' => [['media' => ['not_a_loader' => []]]];
        yield 'empty map' => [['media' => []]];
    }

    /**
     * A string type is echoed verbatim; a non-string type is reported as its debug type, so the author learns
     * what shape they actually wrote.
     *
     * @return iterable<string, array{mixed, string}>
     */
    public static function rejectsUnknownTypeProvider(): iterable
    {
        yield 'unregistered type name' => ['Sw:Does:NotExist', 'Sw:Does:NotExist'];
        yield 'blank type' => ['', ''];
        yield 'null type reported as its debug type' => [null, 'null'];
        yield 'non-string type reported as its debug type' => [123, 'int'];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function synthesizesInputPerTierProvider(): iterable
    {
        yield 'tier A string' => [['media' => 'mediaId'], true];
        yield 'tier B single-key map' => [['media' => ['entity' => ['property' => 'mediaId']]], true];
        yield 'tier C canonical' => [['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']]], false];
    }

    /**
     * @return iterable<string, array{mixed, mixed, mixed, mixed}>
     */
    public static function passesThroughScalarFacetProvider(): iterable
    {
        yield 'scalar resolves passes through unchanged and synthesizes no inputs' => ['not-a-map', [], 'not-a-map', []];
        yield 'scalar inputs passes through unchanged' => [[], 'not-a-map', [], 'not-a-map'];
    }

    /**
     * Each row drives the same tier-A bare-string walk; the reference FQCN's branch selects the entity vs. collection
     * variant, and both branches carry the identical zero-match and many-match hard errors.
     *
     * @return iterable<string, array{ResolvedByLoaderBranch, class-string, string, array<string, class-string>, string}>
     */
    public static function throwsTierADerivationFailureProvider(): iterable
    {
        yield 'entity branch, no producing entity' => [
            ResolvedByLoaderBranch::Entity,
            MediaEntity::class,
            'mediaId',
            ['product' => ProductEntity::class],
            'no registered entity',
        ];

        yield 'entity branch, two producing entities' => [
            ResolvedByLoaderBranch::Entity,
            MediaEntity::class,
            'mediaId',
            ['media' => MediaEntity::class, 'media_clone' => MediaEntity::class],
            'multiple registered entities (media, media_clone)',
        ];

        yield 'collection branch, no producing entity' => [
            ResolvedByLoaderBranch::EntityCollection,
            MediaCollection::class,
            'mediaIds',
            ['product' => ProductCollection::class],
            'no registered entity',
        ];

        yield 'collection branch, two producing entities' => [
            ResolvedByLoaderBranch::EntityCollection,
            MediaCollection::class,
            'mediaIds',
            ['media' => MediaCollection::class, 'media_clone' => MediaCollection::class],
            'multiple registered entities (media, media_clone)',
        ];
    }

    /**
     * Each row drives the same tier-B single-key loader form whose unauthored entity-name key must be derived from
     * the reference FQCN; the branch selects the entity vs. collection walk, and both walks carry the identical
     * zero-match and many-match hard errors.
     *
     * @return iterable<string, array{ResolvedByLoaderBranch, class-string, string, array<string, class-string>, string}>
     */
    public static function throwsTierBDerivationFailureProvider(): iterable
    {
        yield 'scalar ambiguity' => [
            ResolvedByLoaderBranch::Entity,
            MediaEntity::class,
            'mediaId',
            ['media' => MediaEntity::class, 'media_clone' => MediaEntity::class],
            'multiple registered entities (media, media_clone)',
        ];

        yield 'scalar zero' => [
            ResolvedByLoaderBranch::Entity,
            MediaEntity::class,
            'mediaId',
            ['product' => ProductEntity::class],
            'no registered entity',
        ];

        yield 'collection ambiguity' => [
            ResolvedByLoaderBranch::EntityCollection,
            MediaCollection::class,
            'mediaIds',
            ['media' => MediaCollection::class, 'media_clone' => MediaCollection::class],
            'multiple registered entities (media, media_clone)',
        ];

        yield 'collection zero' => [
            ResolvedByLoaderBranch::EntityCollection,
            MediaCollection::class,
            'mediaIds',
            ['product' => ProductCollection::class],
            'no registered entity',
        ];
    }

    private function expectCanonicalizationError(BindingSpecificationCanonicalizer $canonicalizer, BindingSpecificationDto $dto, string $id): ContentSystemException
    {
        try {
            $canonicalizer->canonicalize($dto, $id);
        } catch (ContentSystemException $exception) {
            return $exception;
        }

        static::fail('Expected a ContentSystemException to be thrown.');
    }

    /**
     * @param array<string, mixed> $resolves
     */
    private function dto(mixed $type, array $resolves): BindingSpecificationDto
    {
        return new BindingSpecificationDto($type, 'label', $resolves, []);
    }

    /**
     * @param array<string, ContentSystemElementTypeSpecification> $types
     * @param array<string, EntityDefinition> $definitions
     */
    private function canonicalizer(
        array $types,
        ContentSystemDataLoaderMap $map,
        array $definitions = [],
        ?SalesChannelDefinitionInstanceRegistry $salesChannelRegistry = null,
    ): BindingSpecificationCanonicalizer {
        $typeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $typeRegistry->method('has')->willReturnCallback(static fn (string $name): bool => \array_key_exists($name, $types));
        $typeRegistry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $types[$name]);

        $mapResolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $mapResolver->method('resolve')->willReturn($map);

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('getDefinitions')->willReturn($definitions);

        if ($salesChannelRegistry === null) {
            $salesChannelRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
            $salesChannelRegistry->method('getSalesChannelDefinitions')->willReturn([]);
        }

        return new BindingSpecificationCanonicalizer($typeRegistry, $mapResolver, $definitionRegistry, $salesChannelRegistry);
    }

    /**
     * @param class-string<Struct> $producedType
     * @param array<string, mixed> $configTemplate
     */
    private function capability(string $producedType, array $configTemplate): LoaderTypeCapability
    {
        return new LoaderTypeCapability($producedType, $configTemplate);
    }

    /**
     * @param array<string, list<LoaderTypeCapability>> $capabilities
     * @param array<string, LoaderConfigSpecification> $specifications
     */
    private function map(array $capabilities, array $specifications): ContentSystemDataLoaderMap
    {
        return new ContentSystemDataLoaderMap($capabilities, $specifications);
    }

    private function entitySpec(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
        ]);
    }

    private function imageType(): ContentSystemElementTypeSpecification
    {
        return $this->typeSpec('image', [
            'media' => $this->reference(MediaEntity::class, required: true),
            'mediaId' => $this->primitive('string'),
        ]);
    }

    /**
     * @param array<string, PropertySpecification> $properties
     */
    private function typeSpec(string $name, array $properties): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification($name, $name, '', null, null, new CopilotSpecification('', []), $properties, []);
    }

    private function reference(string $fqcn, bool $required = false): PropertySpecification
    {
        return new PropertySpecification('property', new PropertyType($fqcn, false, null, null), $required, '', '', null);
    }

    private function primitive(string $type): PropertySpecification
    {
        return new PropertySpecification('property', new PropertyType($type, false, null, null), false, '', '', null);
    }

    /**
     * @param array<string, class-string<Entity>> $entities
     *
     * @return array<string, EntityDefinition>
     */
    private function definitions(array $entities): array
    {
        $definitions = [];
        foreach ($entities as $name => $class) {
            $definitions[$name] = $this->entityDefinition($name, $class);
        }

        return $definitions;
    }

    /**
     * Builds definitions that produce the given classes for the branch under test: an entity-class producer for the
     * entity branch, a collection-class producer for the collection branch, so one provider row can drive either walk.
     *
     * @param array<string, class-string> $producers
     *
     * @return array<string, EntityDefinition>
     */
    private function branchDefinitions(ResolvedByLoaderBranch $branch, array $producers): array
    {
        $producerMethod = match ($branch) {
            ResolvedByLoaderBranch::Entity => 'getEntityClass',
            ResolvedByLoaderBranch::EntityCollection => 'getCollectionClass',
        };

        $definitions = [];
        foreach ($producers as $name => $class) {
            $definition = static::createStub(EntityDefinition::class);
            $definition->method('getEntityName')->willReturn($name);
            $definition->method($producerMethod)->willReturn($class);
            $definitions[$name] = $definition;
        }

        return $definitions;
    }

    /**
     * @param class-string<Entity> $class
     */
    private function entityDefinition(string $name, string $class): EntityDefinition
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($name);
        $definition->method('getEntityClass')->willReturn($class);

        return $definition;
    }

    /**
     * @param class-string<EntityCollection<covariant Entity>> $class
     */
    private function collectionEntityDefinition(string $name, string $class): EntityDefinition
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($name);
        $definition->method('getCollectionClass')->willReturn($class);

        return $definition;
    }

    /**
     * @param class-string<Entity> $class
     */
    private function mappingDefinition(string $name, string $class): MappingEntityDefinition
    {
        $definition = static::createStub(MappingEntityDefinition::class);
        $definition->method('getEntityName')->willReturn($name);
        $definition->method('getEntityClass')->willReturn($class);

        return $definition;
    }
}
