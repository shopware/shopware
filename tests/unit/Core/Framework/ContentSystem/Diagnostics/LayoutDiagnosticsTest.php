<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Framework\ContentSystem\Resolution\AvailableContextResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Shopware\Core\Framework\ContentSystem\Resolution\ElementResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * @internal
 */
#[CoversClass(LayoutDiagnostics::class)]
class LayoutDiagnosticsTest extends TestCase
{
    #[TestDox('produces no binding error when root-ambient context satisfies a required reference')]
    public function testRootAmbientSatisfiesRequired(): void
    {
        $tree = [new ContentElement('root-1', 'Sw:Block')];

        $rootContext = [new ProvidedContext(
            contextKey: 'product',
            fqcn: SalesChannelProductEntity::class,
            contextType: ContextType::Single,
            providerElementId: VirtualRootWrapper::VIRTUAL_ROOT_ID,
            distribution: DistributionStrategy::Broadcast,
        )];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->reference('product', SalesChannelProductEntity::class, required: true)->build()])
            ->analyze($tree, $rootContext)->report;

        static::assertSame([], $report->bindingErrors());
    }

    #[TestDox('treats a required primitive carrying an authored value and no default as resolvable')]
    public function testRequiredPrimitiveWithAuthoredValueResolves(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Block', [], ['headline' => 'Authored headline'])];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->primitive('headline', 'string', required: true)->build()])
            ->analyze($tree, [])->report;

        static::assertSame([], $report->bindingErrors());
    }

    #[TestDox('resolves a required reference via valid applied wiring, producing no unresolved_required binding error and keeping the element well-formed')]
    public function testValidAppliedWiringResolvesRequiredReferenceAndStaysWellFormed(): void
    {
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willReturn(SalesChannelProductEntity::class);

        $analysis = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->reference('product', SalesChannelProductEntity::class, required: true)->build()],
            // A Stored resolution implies a registered loader, so the map must carry the source's config
            // specification (here: no propertyReference keys, so unfilled_required_input never fires).
            map: $this->loaderConfigMap('entity', new LoaderConfigSpecification([])),
            loaderProvider: $this->loaderProvider($loader),
        )->analyze([$element], []);

        static::assertTrue($analysis->report->isWellFormed());
        static::assertSame([], $analysis->report->bindingErrors());
        static::assertNotNull($analysis->resolutions['el-1'][0]->resolved);
        static::assertSame(CandidateOrigin::Stored, $analysis->resolutions['el-1'][0]->resolved->origin);
    }

    #[DataProvider('filledInputValueProvider')]
    #[TestDox('emits no unfilled_required_input, and stays resolvable, when the stored-wired input property carries a value')]
    public function testStoredRequiredReferenceWithFilledInputIsResolvable(string $storedValue): void
    {
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'media_loader', static::createStub(AbstractContentDataLoaderConfig::class))
            ->withProperty('productId', $storedValue)
            ->build();

        $report = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()
                ->reference('product', SalesChannelProductEntity::class, required: true)
                ->primitive('productId', 'string')
                ->build()],
            $this->loaderConfigMap('media_loader', new LoaderConfigSpecification([
                new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            ])),
            $this->encodingSerializers(['property' => 'productId']),
            $this->storedLoaderProvider(SalesChannelProductEntity::class),
        )->analyze([$element], [])->report;

        static::assertTrue($report->isResolvable());
        static::assertSame([], $report->bindingErrors());
    }

    #[TestDox('accepts an unsatisfied required reference in the well-formedness subset, emits no binding errors and exposes the analysed element in the resolutions map')]
    public function testWellFormednessSubsetIgnoresBinding(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Block')];

        $analysis = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->reference('product', SalesChannelProductEntity::class, required: true)->build()])
            ->analyze($tree, null);

        static::assertTrue($analysis->report->isWellFormed());
        static::assertSame([], $analysis->report->bindingErrors());
        static::assertArrayHasKey('el-1', $analysis->resolutions);
    }

    #[TestDox('does not flag a deep required consumer when an intermediate redistributes the matching root-ambient context')]
    public function testRedistributingIntermediateSatisfiesDeepRequiredChain(): void
    {
        $level2 = ContentElementBuilder::create('Sw:Block', 'level-2')
            ->withConsumer('product', ContextType::Single, required: true)
            ->build();
        $root = ContentElementBuilder::create('Sw:Block', 'root-1')
            ->withConsumer('product', ContextType::Single, redistribute: true)
            ->withSlot('content', [$level2])
            ->build();

        $rootContext = [new ProvidedContext(
            contextKey: 'product',
            fqcn: SalesChannelProductEntity::class,
            contextType: ContextType::Single,
            providerElementId: VirtualRootWrapper::VIRTUAL_ROOT_ID,
            distribution: DistributionStrategy::Broadcast,
        )];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()])
            ->analyze([$root], $rootContext)->report;

        static::assertSame([], $report->bindingErrors());
    }

    #[TestDox('backs a declared provider via valid applied wiring so a descendant consumer requiring that context is no longer broken_required_chain')]
    public function testAppliedWiringBacksDeclaredProviderAndSatisfiesDescendantChain(): void
    {
        $child = ContentElementBuilder::create('Sw:Block', 'child-1')
            ->withConsumer('product', ContextType::Single, required: true)
            ->build();
        $root = ContentElementBuilder::create('Sw:Provider', 'root-1')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withDataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->withSlot('content', [$child])
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willReturn(SalesChannelProductEntity::class);

        $analysis = $this->diagnostics(
            [
                'Sw:Provider' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Provider')->reference('product', SalesChannelProductEntity::class)->build(),
                'Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build(),
            ],
            loaderProvider: $this->loaderProvider($loader),
        )->analyze([$root], []);

        static::assertSame([], $analysis->report->bindingErrors());
        static::assertNotNull($analysis->resolutions['root-1'][0]->resolved);
        static::assertSame(CandidateOrigin::Stored, $analysis->resolutions['root-1'][0]->resolved->origin);
    }

    #[TestDox('emits no unfilled_required_input when parent context satisfies a required reference instead of stored wiring')]
    public function testParentContextSatisfiedReferenceDoesNotGateOnUnfilledInput(): void
    {
        // The element also carries a stored requirement for "product", but its loader produces CategoryEntity —
        // not the declared SalesChannelProductEntity — so ElementResolver's Stored candidate fails to resolve
        // and the sole matching parent context wins the pick instead. This isolates the "origin !== Stored"
        // guard: the stored requirement is genuinely present, so if that guard were removed, execution would
        // reach the unfilled-input check below and gate on the empty "productId", turning bindingErrors() non-empty.
        $element = ContentElementBuilder::create('Sw:Block', 'root-1')
            ->withDataRequirement('product', 'media_loader', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $rootContext = [new ProvidedContext(
            contextKey: 'product',
            fqcn: SalesChannelProductEntity::class,
            contextType: ContextType::Single,
            providerElementId: VirtualRootWrapper::VIRTUAL_ROOT_ID,
            distribution: DistributionStrategy::Broadcast,
        )];

        $report = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()
                ->reference('product', SalesChannelProductEntity::class, required: true)
                ->primitive('productId', 'string')
                ->build()],
            $this->loaderConfigMap('media_loader', new LoaderConfigSpecification([
                new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            ])),
            $this->encodingSerializers(['property' => 'productId']),
            $this->storedLoaderProvider(CategoryEntity::class),
        )->analyze([$element], $rootContext)->report;

        static::assertSame([], $report->bindingErrors());
    }

    #[TestDox('emits no unfilled_required_input for an optional reference that stored wiring resolves, even when its input property is empty')]
    public function testOptionalStoredReferenceDoesNotGateOnUnfilledInput(): void
    {
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'media_loader', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $report = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()
                ->reference('product', SalesChannelProductEntity::class, required: false)
                ->primitive('productId', 'string')
                ->build()],
            $this->loaderConfigMap('media_loader', new LoaderConfigSpecification([
                new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            ])),
            $this->encodingSerializers(['property' => 'productId']),
            $this->storedLoaderProvider(SalesChannelProductEntity::class),
        )->analyze([$element], [])->report;

        static::assertSame([], $report->bindingErrors());
    }

    #[TestDox('does not gate a required reference whose loader declares only a defaulted propertyReference key (the navigation shape)')]
    public function testDefaultedPropertyReferenceKeyNeverGates(): void
    {
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('tree', 'navigation_loader', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $report = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()
                ->reference('tree', SalesChannelProductEntity::class, required: true)
                ->primitive('activeProperty', 'string')
                ->build()],
            $this->loaderConfigMap('navigation_loader', new LoaderConfigSpecification([
                new ConfigKeySpecification('activeProperty', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true),
            ])),
            $this->encodingSerializers(['activeProperty' => 'activeProperty']),
            $this->storedLoaderProvider(SalesChannelProductEntity::class),
        )->analyze([$element], [])->report;

        static::assertSame([], $report->bindingErrors());
    }

    #[TestDox('emits no unfilled_required_input when a required propertyReference config value is not a string')]
    public function testNonStringConfiguredPropertyReferenceDoesNotGate(): void
    {
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'media_loader', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $report = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()
                ->reference('product', SalesChannelProductEntity::class, required: true)
                ->primitive('productId', 'string')
                ->build()],
            $this->loaderConfigMap('media_loader', new LoaderConfigSpecification([
                new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            ])),
            $this->encodingSerializers(['property' => ['not', 'a', 'string']]),
            $this->storedLoaderProvider(SalesChannelProductEntity::class),
        )->analyze([$element], [])->report;

        static::assertSame([], $report->bindingErrors());
    }

    #[TestDox('diagnoses a replacement that stored its new type primitive default as resolvable')]
    public function testReplacementWithSeededDefaultIsDiagnosedResolvable(): void
    {
        $specs = ['Sw:New' => ContentSystemElementTypeSpecificationBuilder::create('Sw:New')->primitive('headline', 'string', required: true, default: 'Default headline')->build()];

        // ReplaceElement seeds the new type's default (fully covered in ReplaceElementTest); here we pin the
        // replacement output — the new component plus the seeded default — so the diagnostics assertion cannot pass
        // vacuously on a no-op replacement, then assert the strict primitive rule credits the stored value so the
        // replaced tree diagnoses as resolvable.
        $bindingRegistry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $bindingRegistry->method('all')->willReturn([]);
        $bindingApplicator = new BindingApplicator(static::createStub(DataLoaderConfigSerializerProvider::class));

        $replaced = (new ReplaceElement($this->registry($specs), 'el', 'Sw:New', $bindingRegistry, $bindingApplicator))->apply([new ContentElement('el', 'Sw:Old')]);

        static::assertSame('Sw:New', $replaced[0]->getComponent());
        static::assertSame('Default headline', $replaced[0]->getProperty('headline'));
        static::assertSame([], $this->diagnostics($specs)->analyze($replaced, [])->report->bindingErrors());
    }

    #[TestDox('emits an orphaned_provider warning without blocking when a provider has no consumer in scope')]
    public function testOrphanedProviderWarning(): void
    {
        $root = ContentElementBuilder::create('Sw:Block', 'root-1')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('content', [new ContentElement('child-1', 'Sw:Block')])
            ->build();

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()])->analyze([$root], null)->report;

        static::assertTrue($report->isWellFormed());
        $warning = $this->single(array_filter($report->violations, static fn (Violation $v): bool => $v->code === ViolationCode::OrphanedProvider));
        static::assertSame('root-1', $warning->elementId);
    }

    #[TestDox('reports a required reference whose only candidates are incomplete loaders as unresolved_required, not ambiguous_required')]
    public function testIncompleteLoaderCandidatesAreUnresolvedNotAmbiguous(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Block')];

        // Each loader's specification requires a "property" config key its empty template does not fill, so the
        // derived residual is non-empty and both candidates are incomplete.
        $requiresProperty = new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
        ]);

        $map = new ContentSystemDataLoaderMap(
            [
                'category_a' => [new LoaderTypeCapability(CategoryEntity::class)],
                'category_b' => [new LoaderTypeCapability(CategoryEntity::class)],
            ],
            [
                'category_a' => $requiresProperty,
                'category_b' => $requiresProperty,
            ],
        );

        $report = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->reference('category', CategoryEntity::class, required: true)->build()],
            $map,
        )->analyze($tree, [])->report;

        static::assertSame(ViolationCode::UnresolvedRequired, $this->onlyBindingError($report->bindingErrors())->code);
    }

    #[TestDox('keys the violation on the reference property and names the configured key when the wired property is not declared on the type')]
    public function testUnfilledInputKeysOnReferenceWhenConfiguredPropertyUndeclared(): void
    {
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'media_loader', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $report = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()
                ->reference('product', SalesChannelProductEntity::class, required: true)
                ->build()],
            $this->loaderConfigMap('media_loader', new LoaderConfigSpecification([
                new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            ])),
            $this->encodingSerializers(['property' => 'ghostProperty']),
            $this->storedLoaderProvider(SalesChannelProductEntity::class),
        )->analyze([$element], [])->report;

        $error = $this->onlyBindingError($report->bindingErrors());
        static::assertSame(ViolationCode::UnfilledRequiredInput, $error->code);
        static::assertSame('product', $error->key);
        static::assertSame('Required property "product" is wired from "ghostProperty", which has no value.', $error->message);
    }

    #[TestDox('produces a broken_required_chain binding error for a required acceptsContext with no provider')]
    public function testBrokenRequiredChain(): void
    {
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withConsumer('product', ContextType::Single, required: true)
            ->build();

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()])->analyze([$element], [])->report;

        static::assertSame(ViolationCode::BrokenRequiredChain, $this->onlyBindingError($report->bindingErrors())->code);
    }

    #[TestDox('flags a deep required consumer reached only through a non-redistributing intermediate as broken_required_chain at that consumer, leaving the intermediate satisfied')]
    public function testNonRedistributingIntermediateBreaksDeepRequiredChain(): void
    {
        $level3 = ContentElementBuilder::create('Sw:Block', 'level-3')
            ->withConsumer('product', ContextType::Single, required: true)
            ->build();
        $level2 = ContentElementBuilder::create('Sw:Block', 'level-2')
            ->withConsumer('product', ContextType::Single, required: true)
            ->withSlot('content', [$level3])
            ->build();
        $root = ContentElementBuilder::create('Sw:Provider', 'root-1')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('content', [$level2])
            ->build();

        $report = $this->diagnostics(
            [
                'Sw:Provider' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Provider')->reference('product', SalesChannelProductEntity::class)->build(),
                'Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build(),
            ],
            new ContentSystemDataLoaderMap(
                ['product_loader' => [new LoaderTypeCapability(SalesChannelProductEntity::class)]],
                ['product_loader' => new LoaderConfigSpecification([])],
            ),
            $this->decodingSerializers(),
        )->analyze([$root], [])->report;

        $error = $this->onlyBindingError($report->bindingErrors());
        static::assertSame(ViolationCode::BrokenRequiredChain, $error->code);
        static::assertSame('level-3', $error->elementId);
    }

    #[TestDox('flags a descendant requiring a declared provider whose own property does not resolve on the providing element as broken_required_chain')]
    public function testUnbackedDeclaredProviderBreaksDescendantChain(): void
    {
        $child = ContentElementBuilder::create('Sw:Block', 'child-1')
            ->withConsumer('product', ContextType::Single, required: true)
            ->build();
        $root = ContentElementBuilder::create('Sw:Provider', 'root-1')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('content', [$child])
            ->build();

        $report = $this->diagnostics([
            'Sw:Provider' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Provider')->reference('product', SalesChannelProductEntity::class)->build(),
            'Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build(),
        ])->analyze([$root], [])->report;

        $error = $this->onlyBindingError($report->bindingErrors());
        static::assertSame(ViolationCode::BrokenRequiredChain, $error->code);
        static::assertSame('child-1', $error->elementId);
    }

    #[TestDox('reports a duplicate element id across roots as an intrinsic error')]
    public function testDuplicateElementId(): void
    {
        $tree = [new ContentElement('dup', 'Sw:Block'), new ContentElement('dup', 'Sw:Block')];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()])->analyze($tree, null)->report;

        static::assertFalse($report->isWellFormed());
        static::assertSame(ViolationCode::DuplicateElementId, $this->onlyIntrinsicError($report->intrinsicErrors())->code);
    }

    #[TestDox('reports an unregistered component as an intrinsic error')]
    public function testUnregisteredComponent(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Missing')];

        $report = $this->diagnostics([])->analyze($tree, null)->report;

        static::assertFalse($report->isWellFormed());
        static::assertSame(ViolationCode::UnregisteredComponent, $this->onlyIntrinsicError($report->intrinsicErrors())->code);
    }

    #[TestDox('produces an invalid_config intrinsic error for a data requirement naming an unknown entity')]
    public function testInvalidConfigForUnknownEntity(): void
    {
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willThrowException(ContentSystemException::unknownLoaderEntity('prodct'));

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()], loaderProvider: $this->loaderProvider($loader))
            ->analyze([$element], null)->report;

        static::assertFalse($report->isWellFormed());
        static::assertSame(ViolationCode::InvalidConfig, $this->onlyIntrinsicError($report->intrinsicErrors())->code);
    }

    #[TestDox('reports an undecodable applied config on a declared reference as invalid_config, never mismatched_reference_type')]
    public function testUndecodableConfigOnDeclaredReferenceIsInvalidConfigNotMismatch(): void
    {
        // The reference property IS declared here (unlike the unknown-entity case), so the
        // mismatch check would run if the config resolved. It does not: resolveType throws a client-defect,
        // so the single intrinsic error must be InvalidConfig and never MismatchedReferenceType.
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willThrowException(ContentSystemException::unknownLoaderEntity('prodct'));

        $report = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->reference('product', SalesChannelProductEntity::class, required: true)->build()],
            loaderProvider: $this->loaderProvider($loader),
        )->analyze([$element], [])->report;

        static::assertFalse($report->isWellFormed());
        static::assertSame(ViolationCode::InvalidConfig, $this->onlyIntrinsicError($report->intrinsicErrors())->code);
    }

    #[TestDox('produces an unresolved_required binding error for a required reference with no candidate')]
    public function testUnresolvedRequired(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Block')];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->reference('product', SalesChannelProductEntity::class, required: true)->build()])
            ->analyze($tree, [])->report;

        static::assertSame(ViolationCode::UnresolvedRequired, $this->onlyBindingError($report->bindingErrors())->code);
    }

    #[TestDox('produces an ambiguous_required binding error carrying candidates when two complete loaders match')]
    public function testAmbiguousRequired(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Block')];

        $map = new ContentSystemDataLoaderMap(
            [
                'category_a' => [new LoaderTypeCapability(CategoryEntity::class)],
                'category_b' => [new LoaderTypeCapability(CategoryEntity::class)],
            ],
            [
                'category_a' => new LoaderConfigSpecification([]),
                'category_b' => new LoaderConfigSpecification([]),
            ],
        );

        $report = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->reference('category', CategoryEntity::class, required: true)->build()],
            $map,
            $this->decodingSerializers(),
        )->analyze($tree, [])->report;

        $error = $this->onlyBindingError($report->bindingErrors());
        static::assertSame(ViolationCode::AmbiguousRequired, $error->code);
        static::assertCount(2, $error->candidates);
    }

    #[TestDox('raises an independent mismatched_reference_type intrinsic violation and unresolved_required binding violation for a required reference whose applied wiring produces the wrong type')]
    public function testMismatchedAppliedWiringRaisesIntrinsicAndBindingViolationsIndependently(): void
    {
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willReturn(CategoryEntity::class);

        $report = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->reference('product', SalesChannelProductEntity::class, required: true)->build()],
            loaderProvider: $this->loaderProvider($loader),
        )->analyze([$element], [])->report;

        static::assertFalse($report->isWellFormed());
        static::assertSame(ViolationCode::MismatchedReferenceType, $this->onlyIntrinsicError($report->intrinsicErrors())->code);
        static::assertSame(ViolationCode::UnresolvedRequired, $this->onlyBindingError($report->bindingErrors())->code);
    }

    /**
     * @param array<string, mixed> $properties
     */
    #[DataProvider('producesUnresolvedRequiredPrimitiveProvider')]
    #[TestDox('produces an unresolved_required binding error for a required primitive without a default and no usable value')]
    public function testRequiredPrimitiveWithoutValueIsUnresolved(array $properties): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Block', [], $properties)];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->primitive('headline', 'string', required: true)->build()])
            ->analyze($tree, [])->report;

        static::assertSame(ViolationCode::UnresolvedRequired, $this->onlyBindingError($report->bindingErrors())->code);
    }

    #[TestDox('reports a required primitive carrying a type default but no authored value as unresolved_required')]
    public function testRequiredPrimitiveWithDefaultButNoValueIsUnresolved(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Block')];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->primitive('headline', 'string', required: true, default: 'Default headline')->build()])
            ->analyze($tree, [])->report;

        static::assertSame(ViolationCode::UnresolvedRequired, $this->onlyBindingError($report->bindingErrors())->code);
    }

    /**
     * @param array<string, mixed> $properties
     */
    #[DataProvider('unfilledRequiredInputProvider')]
    #[TestDox('emits one unfilled_required_input keyed on the input property, naming both keys, when stored wiring binds a required reference to a value-less property')]
    public function testStoredRequiredReferenceWithUnfilledInputGates(array $properties): void
    {
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'media_loader', static::createStub(AbstractContentDataLoaderConfig::class))
            ->withProperties($properties)
            ->build();

        $report = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()
                ->reference('product', SalesChannelProductEntity::class, required: true)
                ->primitive('productId', 'string')
                ->build()],
            $this->loaderConfigMap('media_loader', new LoaderConfigSpecification([
                new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            ])),
            $this->encodingSerializers(['property' => 'productId']),
            $this->storedLoaderProvider(SalesChannelProductEntity::class),
        )->analyze([$element], [])->report;

        static::assertFalse($report->isResolvable());
        $error = $this->onlyBindingError($report->bindingErrors());
        static::assertSame(ViolationCode::UnfilledRequiredInput, $error->code);
        static::assertSame('productId', $error->key);
        static::assertSame('Required property "product" is wired from "productId", which has no value.', $error->message);
    }

    #[TestDox('emits one unfilled_required_input per unfilled required propertyReference key for a multi-reference loader')]
    public function testMultiReferenceLoaderEmitsOneViolationPerUnfilledInput(): void
    {
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'pair_loader', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $report = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()
                ->reference('product', SalesChannelProductEntity::class, required: true)
                ->primitive('productId', 'string')
                ->primitive('productSku', 'string')
                ->build()],
            $this->loaderConfigMap('pair_loader', new LoaderConfigSpecification([
                new ConfigKeySpecification('idProperty', ConfigKeyKind::PropertyReference, 'string', required: true),
                new ConfigKeySpecification('skuProperty', ConfigKeyKind::PropertyReference, 'string', required: true),
            ])),
            $this->encodingSerializers(['idProperty' => 'productId', 'skuProperty' => 'productSku']),
            $this->storedLoaderProvider(SalesChannelProductEntity::class),
        )->analyze([$element], [])->report;

        $errors = $report->bindingErrors();
        static::assertCount(2, $errors);
        static::assertCount(2, array_filter($errors, static fn (Violation $v): bool => $v->code === ViolationCode::UnfilledRequiredInput));
        static::assertEqualsCanonicalizing(
            ['productId', 'productSku'],
            array_map(static fn (Violation $v): ?string => $v->key, $errors),
        );
    }

    #[TestDox('propagates a non-client-defect exception during config resolution instead of converting it to invalid_config')]
    public function testInternalFaultPropagates(): void
    {
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willThrowException(ContentSystemException::layoutNotFound('x'));

        $diagnostics = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()], loaderProvider: $this->loaderProvider($loader));

        $this->expectExceptionObject(ContentSystemException::layoutNotFound('x'));

        $diagnostics->analyze([$element], null);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unfilledRequiredInputProvider(): iterable
    {
        yield 'no stored value' => [[]];
        yield 'stored explicit null counts as no value' => [['productId' => null]];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function filledInputValueProvider(): iterable
    {
        yield 'non-empty stored value' => ['a-product-id'];
        yield 'empty string counts as filled (null is the sole empty sentinel)' => [''];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function producesUnresolvedRequiredPrimitiveProvider(): iterable
    {
        yield 'no stored value' => [[]];
        yield 'stored explicit null counts as no value' => [['headline' => null]];
    }

    /**
     * @param array<string, ContentSystemElementTypeSpecification> $specs
     */
    private function registry(array $specs): AbstractContentSystemElementTypeRegistry
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return $registry;
    }

    /**
     * @param array<string, ContentSystemElementTypeSpecification> $specs
     */
    private function diagnostics(
        array $specs,
        ?ContentSystemDataLoaderMap $map = null,
        ?DataLoaderConfigSerializerProvider $serializers = null,
        ?DataLoaderProvider $loaderProvider = null,
    ): LayoutDiagnostics {
        $registry = $this->registry($specs);

        $typeResolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $typeResolver->method('resolve')->willReturn($map ?? new ContentSystemDataLoaderMap([], []));

        // Share one loader provider between the resolver (stored-candidate) and the RootContextMapper
        // (mismatch check) so both resolve a stored requirement's produced type consistently.
        $loaderProvider ??= static::createStub(DataLoaderProvider::class);
        $serializers ??= static::createStub(DataLoaderConfigSerializerProvider::class);

        $elementResolver = new ElementResolver(
            $registry,
            $typeResolver,
            $serializers,
            $loaderProvider,
        );

        return new LayoutDiagnostics(
            new AvailableContextResolver($registry, $elementResolver),
            $elementResolver,
            $registry,
            new RootContextMapper($loaderProvider),
            $typeResolver,
            $serializers,
        );
    }

    /**
     * @param AbstractContentDataLoader<Struct> $loader
     */
    private function loaderProvider(AbstractContentDataLoader $loader): DataLoaderProvider
    {
        $provider = static::createStub(DataLoaderProvider::class);
        $provider->method('get')->willReturn($loader);

        return $provider;
    }

    private function decodingSerializers(): DataLoaderConfigSerializerProvider
    {
        $serializers = static::createStub(DataLoaderConfigSerializerProvider::class);
        $serializers->method('decode')->willReturn(static::createStub(AbstractContentDataLoaderConfig::class));

        return $serializers;
    }

    /**
     * @param array<string, mixed> $encoded
     */
    private function encodingSerializers(array $encoded): DataLoaderConfigSerializerProvider
    {
        $serializers = static::createStub(DataLoaderConfigSerializerProvider::class);
        $serializers->method('encode')->willReturn($encoded);

        return $serializers;
    }

    private function loaderConfigMap(string $source, LoaderConfigSpecification $specification): ContentSystemDataLoaderMap
    {
        return new ContentSystemDataLoaderMap([], [$source => $specification]);
    }

    private function storedLoaderProvider(string $producedType): DataLoaderProvider
    {
        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willReturn($producedType);

        return $this->loaderProvider($loader);
    }

    /**
     * @param list<Violation> $errors
     */
    private function onlyIntrinsicError(array $errors): Violation
    {
        return $this->single($errors);
    }

    /**
     * @param list<Violation> $errors
     */
    private function onlyBindingError(array $errors): Violation
    {
        return $this->single($errors);
    }

    /**
     * @param array<Violation> $violations
     */
    private function single(array $violations): Violation
    {
        static::assertCount(1, $violations);

        return array_values($violations)[0];
    }
}
