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
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
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
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ProviderDeliveryKeyResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Framework\ContentSystem\Resolution\AvailableContextResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Shopware\Core\Framework\ContentSystem\Resolution\ElementResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutDiagnostics::class)]
class LayoutDiagnosticsTest extends TestCase
{
    #[TestDox('produces no binding error when root-ambient context satisfies a required reference')]
    public function testRootAmbientSatisfiesRequired(): void
    {
        $tree = [new StoredElement('root-1', 'Sw:Block')];

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

    #[TestDox('resolves a required reference via valid applied wiring, producing no unresolved_required binding error and keeping the element well-formed')]
    public function testValidAppliedWiringResolvesRequiredReferenceAndStaysWellFormed(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
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

    #[TestDox('emits an orphaned_provider warning without blocking when a provider has no consumer in scope')]
    public function testOrphanedProviderWarning(): void
    {
        $root = StoredElementBuilder::create('Sw:Block', 'root-1')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('content', [new StoredElement('child-1', 'Sw:Block')])
            ->build();

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()])->analyze([$root], null)->report;

        static::assertTrue($report->isWellFormed());
        $warning = $this->single(array_filter($report->violations, static fn (Violation $v): bool => $v->code === ViolationCode::OrphanedProvider));
        static::assertSame('root-1', $warning->elementId);
    }

    #[TestDox('reports no property-type violation for a stored value matching its declared primitive type')]
    public function testConformingPropertyValueProducesNoViolation(): void
    {
        $tree = [StoredElementBuilder::create('Sw:Block', 'el-1')->withProperty('count', 5)->build()];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->primitive('count', 'integer')->build()])
            ->analyze($tree, null)->report;

        static::assertTrue($report->isWellFormed());
        static::assertSame([], $report->intrinsicErrors());
    }

    #[TestDox('emits no unfilled_required_input when parent context satisfies a required reference instead of stored wiring')]
    public function testParentContextSatisfiedReferenceDoesNotGateOnUnfilledInput(): void
    {
        // The element also carries a stored requirement for "product", but its loader produces CategoryEntity —
        // not the declared SalesChannelProductEntity — so ElementResolver's Stored candidate fails to resolve
        // and the sole matching parent context wins the pick instead. This isolates the "origin !== Stored"
        // guard: the stored requirement is genuinely present, so if that guard were removed, execution would
        // reach the unfilled-input check below and gate on the empty "productId", turning bindingErrors() non-empty.
        $element = StoredElementBuilder::create('Sw:Block', 'root-1')
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

    #[TestDox('does not flag a deep required consumer when an intermediate redistributes the matching root-ambient context')]
    public function testRedistributingIntermediateSatisfiesDeepRequiredChain(): void
    {
        $level2 = StoredElementBuilder::create('Sw:Block', 'level-2')
            ->withConsumer('product', ContextType::Single, required: true)
            ->build();
        $root = StoredElementBuilder::create('Sw:Block', 'root-1')
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
        $child = StoredElementBuilder::create('Sw:Block', 'child-1')
            ->withConsumer('product', ContextType::Single, required: true)
            ->build();
        $root = StoredElementBuilder::create('Sw:Provider', 'root-1')
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

    #[TestDox('accepts an unsatisfied required reference in the well-formedness subset, emits no binding errors and exposes the analysed element in the resolutions map')]
    public function testWellFormednessSubsetIgnoresBinding(): void
    {
        $tree = [new StoredElement('el-1', 'Sw:Block')];

        $analysis = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->reference('product', SalesChannelProductEntity::class, required: true)->build()])
            ->analyze($tree, null);

        static::assertTrue($analysis->report->isWellFormed());
        static::assertSame([], $analysis->report->bindingErrors());
        static::assertArrayHasKey('el-1', $analysis->resolutions);
    }

    #[TestDox('reports a required reference whose only candidates are incomplete loaders as unresolved_required, not ambiguous_required')]
    public function testIncompleteLoaderCandidatesAreUnresolvedNotAmbiguous(): void
    {
        $tree = [new StoredElement('el-1', 'Sw:Block')];

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

        $error = $this->onlyBindingError($report->bindingErrors());
        static::assertSame(ViolationCode::UnresolvedRequired, $error->code);
        static::assertCount(2, $error->candidates);
    }

    #[TestDox('produces a broken_required_chain binding error for a required acceptsContext with no provider')]
    public function testBrokenRequiredChain(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withConsumer('product', ContextType::Single, required: true)
            ->build();

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()])->analyze([$element], [])->report;

        static::assertSame(ViolationCode::BrokenRequiredChain, $this->onlyBindingError($report->bindingErrors())->code);
    }

    #[TestDox('flags a deep required consumer reached only through a non-redistributing intermediate as broken_required_chain at that consumer, leaving the intermediate satisfied')]
    public function testNonRedistributingIntermediateBreaksDeepRequiredChain(): void
    {
        $level3 = StoredElementBuilder::create('Sw:Block', 'level-3')
            ->withConsumer('product', ContextType::Single, required: true)
            ->build();
        $level2 = StoredElementBuilder::create('Sw:Block', 'level-2')
            ->withConsumer('product', ContextType::Single, required: true)
            ->withSlot('content', [$level3])
            ->build();
        $root = StoredElementBuilder::create('Sw:Provider', 'root-1')
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
        $child = StoredElementBuilder::create('Sw:Block', 'child-1')
            ->withConsumer('product', ContextType::Single, required: true)
            ->build();
        $root = StoredElementBuilder::create('Sw:Provider', 'root-1')
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
        $tree = [new StoredElement('dup', 'Sw:Block'), new StoredElement('dup', 'Sw:Block')];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()])->analyze($tree, null)->report;

        static::assertFalse($report->isWellFormed());
        static::assertSame(ViolationCode::DuplicateElementId, $this->onlyIntrinsicError($report->intrinsicErrors())->code);
    }

    #[TestDox('reports an unregistered component as an intrinsic error')]
    public function testUnregisteredComponent(): void
    {
        $tree = [new StoredElement('el-1', 'Sw:Missing')];

        $report = $this->diagnostics([])->analyze($tree, null)->report;

        static::assertFalse($report->isWellFormed());
        static::assertSame(ViolationCode::UnregisteredComponent, $this->onlyIntrinsicError($report->intrinsicErrors())->code);
    }

    #[TestDox('reports a stored value disagreeing with its declared primitive type as an intrinsic error naming the key and both types')]
    public function testMismatchedPropertyTypeIsIntrinsicError(): void
    {
        $tree = [StoredElementBuilder::create('Sw:Block', 'el-1')->withProperty('count', 'not-an-int')->build()];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->primitive('count', 'integer')->build()])
            ->analyze($tree, null)->report;

        $violation = $this->onlyIntrinsicError($report->intrinsicErrors());

        static::assertFalse($report->isWellFormed());
        static::assertSame(ViolationCode::MismatchedPropertyType, $violation->code);
        static::assertSame('el-1', $violation->elementId);
        static::assertSame('count', $violation->key);
        static::assertSame('Property "count" is declared as "integer" but carries a value of type "string".', $violation->message);
    }

    #[TestDox('reports a collision per declaring element, so the repeat suppression does not collapse two elements')]
    public function testProviderDeliveryCollisionsOnDistinctElementsAreReportedSeparately(): void
    {
        // Dedup discrimination: each owner carries a child, so each collision is raised twice and the
        // suppression has to fire — but it is keyed on the declaring element, not on the violation code
        // alone, so the two owners still report separately. A key too broad to tell them apart would
        // report once and pass every other collision test in this file.
        $ownerA = StoredElementBuilder::create('Sw:Block', 'el-owner-a')
            ->withProvider('product', BroadcastDistributionConfig::aliased('item'))
            ->withProvider('category', BroadcastDistributionConfig::aliased('item'))
            ->withSlot('content', [StoredElementBuilder::create('Sw:Block', 'el-child-a')->build()])
            ->build();
        $ownerB = StoredElementBuilder::create('Sw:Block', 'el-owner-b')
            ->withProvider('manufacturer', BroadcastDistributionConfig::aliased('box'))
            ->withProvider('media', BroadcastDistributionConfig::aliased('box'))
            ->withSlot('content', [StoredElementBuilder::create('Sw:Block', 'el-child-b')->build()])
            ->build();

        $violations = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()])
            ->analyze([$ownerA, $ownerB], null)->report->intrinsicErrors();

        static::assertSame(
            ['el-owner-a', 'el-owner-b'],
            array_map(static fn (Violation $violation): string => $violation->elementId, $violations),
        );
    }

    #[TestDox('produces an unresolved_required binding error for a required reference with no candidate')]
    public function testUnresolvedRequired(): void
    {
        $tree = [new StoredElement('el-1', 'Sw:Block')];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->reference('product', SalesChannelProductEntity::class, required: true)->build()])
            ->analyze($tree, [])->report;

        static::assertSame(ViolationCode::UnresolvedRequired, $this->onlyBindingError($report->bindingErrors())->code);
    }

    #[TestDox('produces an ambiguous_required binding error carrying candidates when two complete loaders match')]
    public function testAmbiguousRequired(): void
    {
        $tree = [new StoredElement('el-1', 'Sw:Block')];

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
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
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

    #[TestDox('emits one unfilled_required_input per unfilled required propertyReference key for a multi-reference loader')]
    public function testMultiReferenceLoaderEmitsOneViolationPerUnfilledInput(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
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
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willThrowException(ContentSystemException::layoutNotFound('x'));

        $diagnostics = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()], loaderProvider: $this->loaderProvider($loader));

        $this->expectExceptionObject(ContentSystemException::layoutNotFound('x'));

        $diagnostics->analyze([$element], null);
    }

    #[TestDox('treats a required primitive carrying an authored value and no default as resolvable')]
    public function testRequiredPrimitiveWithAuthoredValueResolves(): void
    {
        $tree = [StoredElementBuilder::create('Sw:Block', 'el-1')->withProperty('headline', 'Authored headline')->build()];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->primitive('headline', 'string', required: true)->build()])
            ->analyze($tree, [])->report;

        static::assertSame([], $report->bindingErrors());
    }

    #[DataProvider('acceptsFilledInputValueProvider')]
    #[TestDox('emits no unfilled_required_input, and stays resolvable, when the stored-wired input property carries a value')]
    public function testStoredRequiredReferenceWithFilledInputIsResolvable(string $storedValue): void
    {
        $element = $this->mediaLoaderWiredElement(['productId' => $storedValue]);

        $report = $this->analyzeMediaLoaderWiring($element);

        static::assertTrue($report->isResolvable());
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

        $replaced = (new ReplaceElement($this->registry($specs), 'el', 'Sw:New', $bindingRegistry, $bindingApplicator))
            ->apply(new StoredTree([new StoredElement('el', 'Sw:Old')]));

        $report = $this->diagnostics($specs)->analyze($replaced->roots, [])->report;

        static::assertSame('Sw:New', $replaced->roots[0]->component);
        static::assertSame('Default headline', $replaced->roots[0]->property('headline')?->jsonSerialize());
        static::assertSame([], $report->bindingErrors());
    }

    #[TestDox('reports a colliding element with descendants exactly once, naming the element that declares the collision')]
    public function testProviderDeliveryCollisionOnAnElementWithDescendantsIsReportedOnce(): void
    {
        // Descendant axis: the collision sits on an element with three descendants. analyze() calls
        // resolve() once per element and resolve() re-validates the whole ancestor path from scratch, so
        // the same collision surfaces four times — once for the owner, once per descendant. The count is
        // the discriminating assertion: a presence assertion holds with all four entries present, three of
        // them naming a descendant that declares nothing.
        $grandchild = StoredElementBuilder::create('Sw:Block', 'el-grandchild')->build();
        $child = StoredElementBuilder::create('Sw:Block', 'el-child')->withSlot('content', [$grandchild])->build();
        $sibling = StoredElementBuilder::create('Sw:Block', 'el-sibling')->build();
        $owner = StoredElementBuilder::create('Sw:Block', 'el-owner')
            ->withProvider('product', BroadcastDistributionConfig::aliased('item'))
            ->withProvider('category', BroadcastDistributionConfig::aliased('item'))
            ->withSlot('content', [$child, $sibling])
            ->build();

        // The intrinsic ERROR subset: the owner's two providers are consumed by nobody, so the full list
        // also carries two orphaned_provider warnings that say nothing about the collision.
        $violations = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()])
            ->analyze([$owner], null)->report->intrinsicErrors();

        static::assertCount(1, $violations);
        static::assertSame('el-owner', $violations[0]->elementId);
        static::assertSame([], array_values(array_intersect(
            array_map(static fn (Violation $violation): string => $violation->elementId, $violations),
            ['el-child', 'el-grandchild', 'el-sibling'],
        )));
    }

    #[TestDox('produces an unresolved_required binding error for a required primitive whose key is absent from the stored property map')]
    public function testRequiredPrimitiveWithAbsentKeyIsUnresolved(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')->build();

        // Pins the fixture's state: an absent key, which the storage model reports as a null property.
        static::assertNull($element->property('headline'));

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->primitive('headline', 'string', required: true)->build()])
            ->analyze([$element], [])->report;

        static::assertSame(ViolationCode::UnresolvedRequired, $this->onlyBindingError($report->bindingErrors())->code);
    }

    #[TestDox('reports a required primitive carrying a type default but no authored value as unresolved_required')]
    public function testRequiredPrimitiveWithDefaultButNoValueIsUnresolved(): void
    {
        $tree = [new StoredElement('el-1', 'Sw:Block')];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->primitive('headline', 'string', required: true, default: 'Default headline')->build()])
            ->analyze($tree, [])->report;

        static::assertSame(ViolationCode::UnresolvedRequired, $this->onlyBindingError($report->bindingErrors())->code);
    }

    #[TestDox('emits one unfilled_required_input keyed on the input property when the wired input key is absent from the stored property map')]
    public function testStoredRequiredReferenceWithAbsentInputKeyGates(): void
    {
        $element = $this->mediaLoaderWiredElement([]);

        $report = $this->analyzeMediaLoaderWiring($element);

        // Pins the fixture's state: an absent key, which the storage model reports as a null property.
        static::assertNull($element->property('productId'));
        static::assertFalse($report->isResolvable());
        $error = $this->onlyBindingError($report->bindingErrors());
        static::assertSame(ViolationCode::UnfilledRequiredInput, $error->code);
        static::assertSame('productId', $error->key);
        static::assertSame('Required property "product" is wired from "productId", which has no value.', $error->message);
    }

    #[TestDox('reports no style violation for an option the registry knows')]
    public function testRegisteredStyleOptionProducesNoViolation(): void
    {
        $tree = [new StoredElement('el-1', 'Sw:Block', style: new ElementStyle(['align-self' => ['xs' => 'center']]))];

        $report = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()],
            styleOptionRegistry: $this->styleOptionRegistry(['align-self']),
        )->analyze($tree, null)->report;

        static::assertTrue($report->isWellFormed());
        static::assertSame([], $report->intrinsicErrors());
    }

    #[TestDox('emits no unfilled_required_input for an optional reference that stored wiring resolves, even when its input property is empty')]
    public function testOptionalStoredReferenceDoesNotGateOnUnfilledInput(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'media_loader', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $analysis = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()
                ->reference('product', SalesChannelProductEntity::class, required: false)
                ->primitive('productId', 'string')
                ->build()],
            $this->loaderConfigMap('media_loader', new LoaderConfigSpecification([
                new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            ])),
            $this->encodingSerializers(['property' => 'productId']),
            $this->storedLoaderProvider(SalesChannelProductEntity::class),
        )->analyze([$element], []);

        // The Stored pick is the state the required/optional guard is being isolated from: without it, an
        // unresolved optional reference exits at the next guard and still reports no binding error.
        static::assertSame([], $analysis->report->bindingErrors());
        static::assertNotNull($analysis->resolutions['el-1'][0]->resolved);
        static::assertSame(CandidateOrigin::Stored, $analysis->resolutions['el-1'][0]->resolved->origin);
    }

    #[TestDox('does not gate a required reference whose loader declares an optional propertyReference key, mirroring the navigation shape')]
    public function testOptionalPropertyReferenceKeyNeverGates(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
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
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
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

    #[TestDox('keys the violation on the reference property and names the configured key when the wired property is not declared on the type')]
    public function testUnfilledInputKeysOnReferenceWhenConfiguredPropertyUndeclared(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
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

    #[TestDox('reports a style option the registry does not know as an intrinsic error keyed on the option name')]
    public function testUnknownStyleOptionIsIntrinsicError(): void
    {
        $tree = [new StoredElement('el-1', 'Sw:Block', style: new ElementStyle(['gone-option' => ['xs' => 'x']]))];

        $report = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()],
            styleOptionRegistry: $this->styleOptionRegistry(['align-self']),
        )->analyze($tree, null)->report;

        $violation = $this->onlyIntrinsicError($report->intrinsicErrors());

        static::assertFalse($report->isWellFormed());
        static::assertSame(ViolationCode::UnknownStyleOption, $violation->code);
        static::assertSame('gone-option', $violation->key);
        static::assertSame('el-1', $violation->elementId);
    }

    #[TestDox('reports no property-type violation for a stored null under a declared primitive, leaving that to the required-input rule')]
    public function testStoredNullUnderAPrimitiveProducesNoPropertyTypeViolation(): void
    {
        $tree = [StoredElementBuilder::create('Sw:Block', 'el-1')->withProperty('count', null)->build()];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->primitive('count', 'integer')->build()])
            ->analyze($tree, null)->report;

        static::assertTrue($report->isWellFormed());
        static::assertSame([], $report->intrinsicErrors());
    }

    #[TestDox('produces an unresolved_required binding error for a required primitive authored as an explicit null, which is a present stored value')]
    public function testRequiredPrimitiveAuthoredAsNullIsUnresolved(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')->withProperty('headline', null)->build();

        // Pins the state this case turns on: the key is PRESENT and its stored value's variant is null, which is
        // the state a single-term `property($key) === null` satisfaction test would silently credit as resolved.
        $stored = $element->property('headline');

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->primitive('headline', 'string', required: true)->build()])
            ->analyze([$element], [])->report;

        static::assertNotNull($stored);
        static::assertTrue($stored->isNull());
        static::assertSame(ViolationCode::UnresolvedRequired, $this->onlyBindingError($report->bindingErrors())->code);
    }

    #[TestDox('emits one unfilled_required_input keyed on the input property when the wired input is authored as an explicit null, which is a present stored value')]
    public function testStoredRequiredReferenceWithAuthoredNullInputGates(): void
    {
        $element = $this->mediaLoaderWiredElement(['productId' => null]);
        $stored = $element->property('productId');

        $report = $this->analyzeMediaLoaderWiring($element);

        // Pins the state this case turns on: the key is PRESENT and its stored value's variant is null, which is
        // the state a single-term `property($key) !== null` early return would silently credit as filled.
        static::assertNotNull($stored);
        static::assertTrue($stored->isNull());
        static::assertFalse($report->isResolvable());
        $error = $this->onlyBindingError($report->bindingErrors());
        static::assertSame(ViolationCode::UnfilledRequiredInput, $error->code);
        static::assertSame('productId', $error->key);
        static::assertSame('Required property "product" is wired from "productId", which has no value.', $error->message);
    }

    #[TestDox('produces an invalid_config intrinsic error for a data requirement naming an unknown entity')]
    public function testInvalidConfigForUnknownEntity(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
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
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
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

    #[TestDox('does not throw on colliding child-facing provider keys; reports them as invalid_config and gives the element no resolutions')]
    public function testProviderDeliveryCollisionIsEmbeddedAsInvalidConfig(): void
    {
        // Collision axis: distinct provider map keys whose broadcast configs both rename the matched child
        // key to 'item'. The context walk throws providerDeliveryCollision; analyze() must embed it as an
        // invalid_config violation (the write gate's verdict) instead of propagating the raw exception, and
        // the colliding element resolves nothing.
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withProvider('product', BroadcastDistributionConfig::aliased('item'))
            ->withProvider('category', BroadcastDistributionConfig::aliased('item'))
            ->build();

        $analysis = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()])
            ->analyze([$element], null);

        static::assertFalse($analysis->report->isWellFormed());
        $error = $this->onlyIntrinsicError($analysis->report->intrinsicErrors());
        static::assertSame(ViolationCode::InvalidConfig, $error->code);
        static::assertSame('el-1', $error->elementId);
        static::assertSame(
            'Child-facing key "item" is used by both "product" and "category". Each child-facing key must be unique within an element.',
            $error->message,
        );
        static::assertArrayNotHasKey('el-1', $analysis->resolutions);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptsFilledInputValueProvider(): iterable
    {
        yield 'non-empty stored value' => ['a-product-id'];
        yield 'empty string counts as filled (null is the sole empty sentinel)' => [''];
    }

    /**
     * An element whose required `product` reference is wired to the media loader, whose one required
     * propertyReference config key targets `productId`.
     *
     * @param array<string, mixed> $properties
     */
    private function mediaLoaderWiredElement(array $properties): StoredElement
    {
        return StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'media_loader', static::createStub(AbstractContentDataLoaderConfig::class))
            ->withProperties($properties)
            ->build();
    }

    private function analyzeMediaLoaderWiring(StoredElement $element): DiagnosticsReport
    {
        return $this->diagnostics(
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
        ?AbstractContentSystemStyleOptionRegistry $styleOptionRegistry = null,
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
            new AvailableContextResolver($registry, $elementResolver, new ProviderDeliveryKeyResolver()),
            $elementResolver,
            $registry,
            new RootContextMapper($loaderProvider),
            $typeResolver,
            $serializers,
            $styleOptionRegistry ?? $this->styleOptionRegistry([]),
        );
    }

    /**
     * @param list<string> $names
     */
    private function styleOptionRegistry(array $names): AbstractContentSystemStyleOptionRegistry
    {
        $options = [];
        foreach ($names as $name) {
            $options[$name] = new StyleOptionSpecification(
                $name,
                new StyleOptionValueType('string', null, null, null, null),
                true,
                null,
                'core',
            );
        }

        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn($options);

        return $registry;
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
