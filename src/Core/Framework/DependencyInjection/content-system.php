<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\NavigationAliasResolver;
use Shopware\Core\Framework\ContentSystem\Adapter\NoneSpecificationSource;
use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationFactory;
use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\Api\ContentDiagnoseController;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutMutationController;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewController;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewPageBuilder;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewPayloadStore;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\Api\LayoutMutationController;
use Shopware\Core\Framework\ContentSystem\Api\UnknownRequestFieldExceptionListener;
use Shopware\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\DefaultBindingSpecificationSynthesizer;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\DatabaseBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\CachedContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Binding\Validation\TypeConsistentBindingSpecificationValidator;
use Shopware\Core\Framework\ContentSystem\Cache\CacheFinalizer;
use Shopware\Core\Framework\ContentSystem\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Shopware\Core\Framework\ContentSystem\ContentPipeline;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Shopware\Core\Framework\ContentSystem\DraftLayoutChecker;
use Shopware\Core\Framework\ContentSystem\Helper\ContentLayoutMetadataDeriver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\PropertyTypeConformanceValidator;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeConstraints;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ProviderDeliveryKeyResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\BoxSpacingNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\DatabaseStyleOptionLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\YamlStyleOptionLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\CachedContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\ContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Serialization\StyleOptionSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionCollisionDetector;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionConstraintDeriver;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\Field\StoredElementListFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteBoundary;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\StoredTreePreparer;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTreeStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\DatabaseTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeNameResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaultProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\CachedContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\StoredSchemaResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\ElementTypeCollisionDetector;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Shopware\Core\Framework\ContentSystem\Mutation\PageContextConsumerWiring;
use Shopware\Core\Framework\ContentSystem\Mutation\PersistedLayoutMutator;
use Shopware\Core\Framework\ContentSystem\Output\ElementTreePruner;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ContentDataPageEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ContentDecomposedPageEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ContentPageEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ContentResponseEncodingListener;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ResolvedValueIndexEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Format\DataResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\Format\DecomposedResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\Format\FullResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\Format\SkeletonResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\Index\LoaderValueIdentityFactory;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndexFactory;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueFingerprinter;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\Output\SubTreeExtractor;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDeliveryResolver;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDistributor;
use Shopware\Core\Framework\ContentSystem\Rendering\ElementDataResolver;
use Shopware\Core\Framework\ContentSystem\Rendering\ElementLowering;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElementFactory;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedTreeFactory;
use Shopware\Core\Framework\ContentSystem\Rendering\WiringPlanner;
use Shopware\Core\Framework\ContentSystem\Resolution\AvailableContextResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\ElementResolver;
use Shopware\Core\Framework\ContentSystem\SalesChannel\Routing\ContentRouteLoader;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderSchemaGenerator;
use Shopware\Core\Framework\ContentSystem\Validation\ContentLayoutAssignmentWriteValidator;
use Shopware\Core\Framework\ContentSystem\Validation\ContentLayoutWriteValidator;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Entity Definitions
    $services->set(ContentLayoutDefinition::class)
        ->tag('shopware.entity.definition');

    // Scaffolding Services
    $services->set(VirtualRootWrapper::class);
    $services->set(StoredTreePreparer::class)
        ->args([
            service(VirtualRootWrapper::class),
            service(PartialRenderer::class),
        ]);

    // Output Services
    $services->set(PartialRenderer::class)
        ->args([
            service(ElementTreePruner::class),
            service(ContextDependencyAnalyzer::class),
            service(SubTreeExtractor::class),
        ]);

    // Field Serializers
    $services->set(StoredElementListFieldSerializer::class)
        ->args([
            service(ValidatorInterface::class),
            service(DefinitionInstanceRegistry::class),
            service(StoredTreeCodec::class),
            service(ViolationConstraintMapper::class),
            service(LayoutWriteBoundary::class),
            service(StoredTreeConstraints::class),
        ])
        ->tag('shopware.field_serializer');

    // Both directions of the stored forest's wire shape
    $services->set(StoredElementCodec::class)
        ->args([
            service(DataLoaderConfigSerializerProvider::class),
        ]);

    $services->set(StoredTreeCodec::class)
        ->args([
            service(StoredElementCodec::class),
        ]);

    // The write-time constraint descriptor over that same wire shape
    $services->set(StoredTreeConstraints::class)
        ->args([
            service(ContentSystemStyleOptionRegistry::class),
            service(StyleOptionConstraintDeriver::class),
        ]);

    // The descriptor's one element-type-aware rule: a stored property value agrees with its declared type
    $services->set(PropertyTypeConformanceValidator::class)
        ->args([
            service(ContentSystemElementTypeRegistry::class),
        ])
        ->tag('validator.constraint_validator');

    // Write-boundary default seeding (seeds type primitive defaults into every DAL write of the layout field)
    $services->set(PrimitiveDefaultProvider::class);

    $services->set(LayoutDefaultSeeder::class)
        ->args([
            service(ContentSystemElementTypeRegistry::class),
            service(PrimitiveDefaultProvider::class),
        ]);

    // The forest-wide style pass, shared by the write boundary and the draft decode so the two cannot drift
    $services->set(StoredTreeStyleNormalizer::class)
        ->args([
            service(ElementStyleNormalizer::class),
        ]);

    // The single admission point for a layout write: seed type defaults, normalize style, reconcile attribution
    $services->set(LayoutWriteBoundary::class)
        ->args([
            service(LayoutDefaultSeeder::class),
            service(StoredTreeStyleNormalizer::class),
            service(AttributionReconciler::class),
        ]);

    // Content Data Loaders
    $services->set(EntityLoader::class)
        ->args([
            service(SalesChannelDefinitionInstanceRegistry::class),
            service(DefinitionInstanceRegistry::class),
            service(EntityCacheTagResolver::class),
        ])
        ->tag('content_system.data_loader');

    $services->set(EntityCollectionLoader::class)
        ->args([
            service(SalesChannelDefinitionInstanceRegistry::class),
            service(DefinitionInstanceRegistry::class),
            service(EntityCacheTagResolver::class),
        ])
        ->tag('content_system.data_loader');

    // Data Loader Provider with Tagged Locator
    $services->set(DataLoaderProvider::class)
        ->args([
            tagged_locator('content_system.data_loader', null, 'getRequirementType'),
        ]);

    // Config Serializers
    $services->set(EntityLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');

    $services->set(EntityCollectionLoaderConfigSerializer::class)
        ->args([
            service(EntityLoaderConfigSerializer::class),
        ])
        ->tag('content_system.config_serializer');

    // Config Serializer Provider with Tagged Locator
    $services->set(DataLoaderConfigSerializerProvider::class)
        ->args([
            tagged_locator('content_system.config_serializer', null, 'getSource'),
        ]);

    // Config canonicalization for structural comparison (dedup hash, attribution reconciliation)
    $services->set(ConfigCanonicalizer::class);

    // Context Path Resolver
    $services->set(ContextPathResolver::class);

    // Cache Services
    $services->set(EntityCacheTagResolver::class);

    $services->set(CacheFinalizer::class)
        ->args([
            service(CacheTagCollector::class),
        ]);

    $services->set(CacheInvalidationSubscriber::class)
        ->args([
            service(CacheInvalidator::class),
            service(Connection::class),
            service(EntityCacheTagResolver::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_listener');

    // Hydration Services
    $services->set(LoaderInputResolver::class);

    // Render Layers (stored forest -> rendered forest)
    $services->set(RenderedElementFactory::class)
        ->args([
            service(ContentSystemElementTypeRegistry::class),
        ]);

    $services->set(ElementDataResolver::class)
        ->args([
            service(DataLoaderProvider::class),
            service(LoaderInputResolver::class),
            service(LoaderValueIdentityFactory::class),
        ]);

    $services->set(ValueFingerprinter::class);

    $services->set(LoaderValueIdentityFactory::class)
        ->args([
            service(DataLoaderConfigSerializerProvider::class),
            service(ConfigCanonicalizer::class),
            service(ValueFingerprinter::class),
        ]);

    $services->set(ResolvedValueIndexFactory::class)
        ->args([
            service(ContentSystemElementTypeRegistry::class),
            service(ValueFingerprinter::class),
        ]);

    $services->set(ContextDistributor::class)
        ->args([
            service(ContextPathResolver::class),
        ]);

    $services->set(ContextDeliveryResolver::class)
        ->args([
            service(ContextDistributor::class),
            service(ContextPathResolver::class),
        ]);

    $services->set(RenderedTreeFactory::class)
        ->args([
            service(RenderedElementFactory::class),
        ]);

    $services->set(ElementLowering::class)
        ->args([
            service(ElementDataResolver::class),
            service(ContextDeliveryResolver::class),
            service(RenderedTreeFactory::class),
        ]);

    $services->set(WiringPlanner::class)
        ->args([
            service(ProviderDeliveryKeyResolver::class),
        ]);

    // Layout Context Utilities
    $services->set(ContextDependencyAnalyzer::class);
    $services->set(ProviderDeliveryKeyResolver::class);

    // Output Services (Post-Hydration Processing)
    $services->set(ElementTreePruner::class);
    $services->set(SubTreeExtractor::class);

    // Entity Layout Services
    $services->set(EntityLayoutResolver::class);
    $services->set(EntityLayoutContextFactory::class)
        ->args([
            service(EntityLayoutResolver::class),
            service(RootContextMapper::class),
        ]);

    // Domain-Aware Layout Resolution (Header/Footer)
    $services->set(DomainAwareLayoutResolver::class);

    $services->set(NavigationAliasResolver::class);

    // Helper Services
    $services->set(ContentLayoutMetadataDeriver::class);

    // Content Pipeline
    $services->set(ContentPipeline::class)
        ->public()
        ->args([
            service('event_dispatcher'),
            service(StoredTreePreparer::class),
            service(WiringPlanner::class),
            service(ElementLowering::class),
            service(VirtualRootWrapper::class),
            service(PartialRenderer::class),
            service(ResolvedValueIndexFactory::class),
        ]);

    // Rendering Specification Factory
    $services->set(RenderingSpecificationFactory::class);

    // Section Resolvers
    $services->set(RenderingSpecificationResolver::class)
        ->args([
            tagged_iterator('content_system.entity_specification_source'),
            service(RenderingSpecificationFactory::class),
        ])
        ->tag('content_system.section_resolver', ['section' => 'main']);

    // Output Format Handlers
    $services->set(FullResponseFactory::class)
        ->tag('content_system.output_format', ['format' => 'full']);

    $services->set(SkeletonResponseFactory::class)
        ->tag('content_system.output_format', ['format' => 'skeleton']);

    $services->set(DataResponseFactory::class)
        ->tag('content_system.output_format', ['format' => 'data']);

    $services->set(DecomposedResponseFactory::class)
        ->tag('content_system.output_format', ['format' => 'decomposed']);

    // Response Encoding (module-owned wire shape)
    $services->set(ContentPageEncoder::class)
        ->args([
            service(StructEncoder::class),
        ]);

    // The two index-reading formats (decomposed, data) share this encoding of the resolved value index
    $services->set(ResolvedValueIndexEncoder::class)
        ->args([
            service(StructEncoder::class),
        ]);

    $services->set(ContentDecomposedPageEncoder::class)
        ->args([
            service(ResolvedValueIndexEncoder::class),
        ]);

    $services->set(ContentDataPageEncoder::class)
        ->args([
            service(ResolvedValueIndexEncoder::class),
        ]);

    $services->set(ContentResponseEncodingListener::class)
        ->args([
            service(ContentPageEncoder::class),
            service(ContentDecomposedPageEncoder::class),
            service(ContentDataPageEncoder::class),
        ])
        ->tag('kernel.event_subscriber');

    // Schema Services
    $services->set(ContentSystemDataLoaderMapResolver::class)
        ->args([
            service(DataLoaderProvider::class),
        ]);

    $services->set(ContentSystemDataLoaderSchemaGenerator::class)
        ->args([
            service(ContentSystemDataLoaderMapResolver::class),
        ]);

    // Element Type System
    $services->set(ElementTypeSpecificationSerializer::class);

    $services->set(ElementTypeNameResolver::class);

    $services->set(ElementTypeCollisionDetector::class)
        ->args([
            service(ContentSystemElementTypeRegistry::class),
        ]);

    $services->set(YamlTypeLoader::class)
        ->args([
            service(ElementTypeSpecificationSerializer::class),
            service('validator'),
            service(ElementTypeNameResolver::class),
        ])
        ->arg('$directories', [])
        ->tag('content_system.type_loader');

    $services->set(DatabaseTypeLoader::class)
        ->args([
            service(ElementTypeSpecificationSerializer::class),
            service('validator'),
            service(Connection::class),
            param('kernel.environment'),
            service('logger'),
        ])
        ->tag('content_system.type_loader');

    $services->set(ContentSystemElementTypeRegistry::class)
        ->args([
            tagged_iterator('content_system.type_loader'),
        ]);

    $services->set(CachedContentSystemElementTypeRegistry::class)
        ->decorate(ContentSystemElementTypeRegistry::class)
        ->args([
            service(CachedContentSystemElementTypeRegistry::class . '.inner'),
            service('cache.system'),
        ]);

    // Universal Style Option System
    $services->set(StyleOptionSpecificationSerializer::class);

    $services->set(StyleOptionCollisionDetector::class)
        ->args([
            service(ContentSystemStyleOptionRegistry::class),
        ]);

    $services->set(StyleOptionConstraintDeriver::class);

    $services->set(YamlStyleOptionLoader::class)
        ->args([
            service(StyleOptionSpecificationSerializer::class),
            service('validator'),
        ])
        ->arg('$directories', [])
        ->tag('content_system.style_option_loader');

    $services->set(DatabaseStyleOptionLoader::class)
        ->args([
            service(StyleOptionSpecificationSerializer::class),
            service('validator'),
            service(Connection::class),
            param('kernel.environment'),
            service('logger'),
        ])
        ->tag('content_system.style_option_loader');

    $services->set(ContentSystemStyleOptionRegistry::class)
        ->args([
            tagged_iterator('content_system.style_option_loader'),
        ]);

    $services->set(CachedContentSystemStyleOptionRegistry::class)
        ->decorate(ContentSystemStyleOptionRegistry::class)
        ->args([
            service(CachedContentSystemStyleOptionRegistry::class . '.inner'),
            service('cache.system'),
        ]);

    $services->set(BoxSpacingNormalizer::class);

    $services->set(ElementStyleNormalizer::class)
        ->args([
            service(ContentSystemStyleOptionRegistry::class),
            service(BoxSpacingNormalizer::class),
        ]);

    // Binding Specification System
    $services->set(BindingSpecificationSerializer::class);

    // Load-time sugar canonicalizer: expands sugared resolves entries to canonical {loader, config} form before validation
    $services->set(BindingSpecificationCanonicalizer::class)
        ->args([
            service(ContentSystemElementTypeRegistry::class),
            service(ContentSystemDataLoaderMapResolver::class),
            service(DefinitionInstanceRegistry::class),
            service(SalesChannelDefinitionInstanceRegistry::class),
        ]);

    // Synthesizes a type's default binding specification from its properties' resolvedBy keys
    $services->set(DefaultBindingSpecificationSynthesizer::class);

    $services->set(YamlBindingSpecificationLoader::class)
        ->arg('$directories', [])
        ->arg('$nameResolver', service(ElementTypeNameResolver::class))
        ->arg('$synthesizer', service(DefaultBindingSpecificationSynthesizer::class))
        ->arg('$serializer', service(BindingSpecificationSerializer::class))
        ->arg('$canonicalizer', service(BindingSpecificationCanonicalizer::class))
        ->arg('$validator', service('validator'))
        ->tag('content_system.binding_specification_loader');

    $services->set(DatabaseBindingSpecificationLoader::class)
        ->args([
            param('kernel.environment'),
            service(Connection::class),
            service('logger'),
            service(BindingSpecificationSerializer::class),
            service('validator'),
        ])
        ->tag('content_system.binding_specification_loader');

    $services->set(ContentSystemBindingSpecificationRegistry::class)
        ->args([
            tagged_iterator('content_system.binding_specification_loader'),
        ]);

    $services->set(CachedContentSystemBindingSpecificationRegistry::class)
        ->decorate(ContentSystemBindingSpecificationRegistry::class)
        ->args([
            service(CachedContentSystemBindingSpecificationRegistry::class . '.inner'),
            service('cache.system'),
        ]);

    $services->set(TypeConsistentBindingSpecificationValidator::class)
        ->args([
            service(ContentSystemElementTypeRegistry::class),
            service(DataLoaderConfigSerializerProvider::class),
            service(RootContextMapper::class),
            service(ContentSystemDataLoaderMapResolver::class),
        ])
        ->tag('validator.constraint_validator');

    // Write-seam attribution reconciliation: keeps a stored attributedSpecifications entry honest against current wiring
    $services->set(AttributionReconciler::class)
        ->args([
            service(ContentSystemBindingSpecificationRegistry::class),
            service(DataLoaderConfigSerializerProvider::class),
            service(ConfigCanonicalizer::class),
        ]);

    // Apply-side of a binding decision, shared by the bind-element and insert-element mutation ops
    $services->set(BindingApplicator::class)
        ->args([
            service(DataLoaderConfigSerializerProvider::class),
        ]);

    // What an element type stores (as opposed to its hydrated properties): the storageSchema introspection fold
    $services->set(StoredSchemaResolver::class)
        ->args([
            service(ContentSystemBindingSpecificationRegistry::class),
            service(DataLoaderProvider::class),
        ]);

    // Root-source authority: the valid set of root sources (entity types + sections + none) and their resolution
    $services->set(NoneSpecificationSource::class);

    $services->set(RootSourceRegistry::class)
        ->arg('$entitySources', tagged_iterator('content_system.entity_specification_source'))
        ->arg('$sectionSources', tagged_locator('content_system.specification_source', 'section'))
        // $entityTypes set by ContentLayoutAssignableCompilerPass
        ->arg('$entityTypes', [])
        ->arg('$noneSource', service(NoneSpecificationSource::class));

    // Content Route Loader (arg 0 set by ContentRouteCompilerPass)
    $services->set(ContentRouteLoader::class)
        ->args([
            abstract_arg('route definitions, set by ContentRouteCompilerPass'),
        ])
        ->tag('routing.loader');

    // Resolution & Diagnostics
    $services->set(AvailableContextResolver::class)
        ->public()
        ->args([
            service(ContentSystemElementTypeRegistry::class),
            service(ElementResolver::class),
            service(ProviderDeliveryKeyResolver::class),
            service(ContextPathResolver::class),
        ]);

    $services->set(ElementResolver::class)
        ->args([
            service(ContentSystemElementTypeRegistry::class),
            service(ContentSystemDataLoaderMapResolver::class),
            service(DataLoaderConfigSerializerProvider::class),
            service(DataLoaderProvider::class),
        ]);

    $services->set(RootContextMapper::class)
        ->args([
            service(DataLoaderProvider::class),
        ]);

    $services->set(LayoutDiagnostics::class)
        ->args([
            service(AvailableContextResolver::class),
            service(ElementResolver::class),
            service(ContentSystemElementTypeRegistry::class),
            service(RootContextMapper::class),
            service(ContentSystemDataLoaderMapResolver::class),
            service(DataLoaderConfigSerializerProvider::class),
            service(ContentSystemStyleOptionRegistry::class),
            service(ContextPathResolver::class),
        ]);

    $services->set(LayoutGate::class)
        ->args([
            service(LayoutDiagnostics::class),
        ]);

    $services->set(ViolationConstraintMapper::class);

    // Shared read of a layout's immutable root source (in-flight write batch first, then committed row)
    $services->set(LayoutRootSourceReader::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    // Resolvability gate (DAL PreWriteValidationEvent)
    $services->set(ContentLayoutWriteValidator::class)
        ->args([
            service(LayoutGate::class),
            service(ViolationConstraintMapper::class),
            service(RootSourceRegistry::class),
            service(LayoutRootSourceReader::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ContentLayoutAssignmentWriteValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(LayoutRootSourceReader::class),
        ])
        ->tag('kernel.event_subscriber');

    // Shared draft-layout decode (structural gate) for the preview, diagnose and mutation routes
    $services->set(DraftLayoutDecoder::class)
        ->args([
            service(StoredElementCodec::class),
            service(StoredTreeStyleNormalizer::class),
            service(ViolationConstraintMapper::class),
        ]);

    // Remaps the serializer's ExtraAttributesException to a content-system 400 for the strict-mapped admin routes
    $services->set(UnknownRequestFieldExceptionListener::class)
        ->tag('kernel.event_subscriber');

    // Layout Validation
    $services->set(DraftLayoutChecker::class)
        ->args([
            service(LayoutDiagnostics::class),
        ]);

    // Resolve-and-diagnose Action (Admin API)
    $services->set(ContentDiagnoseController::class)
        ->public()
        ->args([
            service(DraftLayoutDecoder::class),
            service(RootSourceRegistry::class),
            service(LayoutDiagnostics::class),
        ]);

    $services->set(ContentPreviewPageBuilder::class)
        ->args([
            service(SalesChannelContextService::class),
            service(RenderingSpecificationResolver::class),
            service(DraftLayoutDecoder::class),
            service(DraftLayoutChecker::class),
            service(ContentPipeline::class),
        ]);

    $services->set(ContentPreviewPayloadStore::class)
        ->args([
            service('cache.system'),
        ]);

    // Preview Action (Admin API)
    $services->set(ContentPreviewController::class)
        ->public()
        ->args([
            service(ContentPreviewPageBuilder::class),
            service(ContentPreviewPayloadStore::class),
        ]);

    // Mutation Pipeline
    $services->set(PageContextConsumerWiring::class);

    $services->set(MutationPipeline::class)
        ->args([
            service(LayoutDiagnostics::class),
            service(PageContextConsumerWiring::class),
        ]);

    // Layout Mutation Actions (Admin API)
    $services->set(LayoutMutationController::class)
        ->public()
        ->args([
            service(DraftLayoutDecoder::class),
            service(MutationPipeline::class),
            service(ContentSystemElementTypeRegistry::class),
            service(RootSourceRegistry::class),
            service(StoredElementCodec::class),
            service(ContentSystemBindingSpecificationRegistry::class),
            service(BindingApplicator::class),
        ]);

    // Persisted Layout Mutation (load by id, mutate, commit through the gates)
    $services->set(PersistedLayoutMutator::class)
        ->args([
            service('lock.factory'),
            service('content_layout.repository'),
            service(RootSourceRegistry::class),
            service(LayoutDiagnostics::class),
        ]);

    // Persisted Layout Mutation Actions (Admin API)
    $services->set(ContentLayoutMutationController::class)
        ->public()
        ->args([
            service(PersistedLayoutMutator::class),
            service(ContentSystemElementTypeRegistry::class),
            service(StoredElementCodec::class),
            service(DraftLayoutDecoder::class),
            service(ContentSystemBindingSpecificationRegistry::class),
            service(BindingApplicator::class),
        ]);
};
