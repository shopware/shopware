<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Shopware\Core\Framework\ContentSystem\Resolution\ElementResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\ContentSystem\Resolution\ResolutionCandidate;
use Shopware\Core\Framework\ContentSystem\Resolution\ResolutionContext;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Tests\Unit\Core\Framework\ContentSystem\Fixture\LoaderConfigSpecificationFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElementResolver::class)]
class ElementResolverTest extends TestCase
{
    #[TestDox('resolves a primitive to a static value carrying type, default and required flag, never blocking')]
    public function testResolvesPrimitiveToStaticValue(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->primitive('headline', 'string', required: true, default: 'Hello')->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderMap([], []),
        );

        static::assertCount(1, $resolutions);
        static::assertSame('headline', $resolutions[0]->key);
        static::assertSame(PropertyKind::Primitive, $resolutions[0]->kind);
        static::assertSame('string', $resolutions[0]->type);
        static::assertSame('Hello', $resolutions[0]->default);
        static::assertTrue($resolutions[0]->required);
        static::assertNull($resolutions[0]->resolved);
        static::assertSame([], $resolutions[0]->candidates);
    }

    #[TestDox('resolves a reference via the single matching ancestor provider, keeping loaders as alternatives')]
    public function testReferenceResolvesViaSingleParent(): void
    {
        $available = [new ProvidedContext(
            contextKey: 'product',
            fqcn: SalesChannelProductEntity::class,
            contextType: ContextType::Single,
            providerElementId: 'root-1',
            distribution: DistributionStrategy::Broadcast,
        )];

        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('product', ProductEntity::class, required: true)->build(),
            new ResolutionContext('el-1', $available),
            new ContentSystemDataLoaderMap(
                ['entity' => [new LoaderTypeCapability(SalesChannelProductEntity::class, ['entity' => 'product'])]],
                ['entity' => LoaderConfigSpecificationFixture::entityProperty()],
            ),
        );

        static::assertSame(PropertyKind::Reference, $resolutions[0]->kind);
        static::assertNotNull($resolutions[0]->resolved);
        static::assertSame(CandidateOrigin::Parent, $resolutions[0]->resolved->origin);
        static::assertSame('root-1', $resolutions[0]->resolved->providerElementId);
        static::assertCount(2, $resolutions[0]->candidates);
    }

    #[TestDox('mints a Root candidate for an available entry flagged root-ambient and resolves the reference to it')]
    public function testRootFlaggedContextMintsRootOriginCandidate(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('product', ProductEntity::class, required: true)->build(),
            new ResolutionContext('el-1', [$this->provided(root: true)]),
            new ContentSystemDataLoaderMap([], []),
        );

        static::assertCount(1, $resolutions[0]->candidates);
        static::assertSame(CandidateOrigin::Root, $resolutions[0]->candidates[0]->origin);
        static::assertNotNull($resolutions[0]->resolved);
        static::assertSame(CandidateOrigin::Root, $resolutions[0]->resolved->origin);
    }

    #[TestDox('prefers the single Root candidate over a coexisting Parent candidate')]
    public function testRootCandidateOutranksParentCandidate(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('product', ProductEntity::class, required: true)->build(),
            new ResolutionContext('el-1', [
                $this->provided(root: false, providerElementId: 'root-1'),
                $this->provided(root: true),
            ]),
            new ContentSystemDataLoaderMap([], []),
        );

        static::assertCount(2, $resolutions[0]->candidates);
        static::assertNotNull($resolutions[0]->resolved);
        static::assertSame(CandidateOrigin::Root, $resolutions[0]->resolved->origin);
        static::assertNull($resolutions[0]->resolved->providerElementId);
    }

    #[TestDox('leaves a reference unresolved when two Root candidates compete, never falling back to a lone Parent candidate')]
    public function testTwoRootCandidatesStayAmbiguousDespiteALoneParent(): void
    {
        // Ambiguity inside the preferred pool is still ambiguity. A fallback to the single Parent candidate
        // would resolve here, which is exactly the silent pick the three-tier order forbids.
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('product', ProductEntity::class, required: true)->build(),
            new ResolutionContext('el-1', [
                $this->provided(root: true, contextKey: 'product'),
                $this->provided(root: true, contextKey: 'featuredProduct'),
                $this->provided(root: false, providerElementId: 'root-1'),
            ]),
            new ContentSystemDataLoaderMap([], []),
        );

        static::assertNull($resolutions[0]->resolved);
        // Pins that the fixture is actually two Roots and one Parent. Without it the test also passes when the
        // flag mapping misclassifies every offer as Parent, which is a different bug with the same outcome.
        static::assertSame(
            [CandidateOrigin::Root, CandidateOrigin::Root, CandidateOrigin::Parent],
            array_map(static fn (ResolutionCandidate $candidate): CandidateOrigin => $candidate->origin, $resolutions[0]->candidates),
        );
    }

    #[TestDox('leaves a reference unresolved when two Parent candidates compete and no Root candidate exists')]
    public function testTwoParentCandidatesStayAmbiguous(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('product', ProductEntity::class, required: true)->build(),
            new ResolutionContext('el-1', [
                $this->provided(root: false, providerElementId: 'root-1', contextKey: 'product'),
                $this->provided(root: false, providerElementId: 'level-2', contextKey: 'item'),
            ]),
            new ContentSystemDataLoaderMap([], []),
        );

        static::assertNull($resolutions[0]->resolved);
        static::assertCount(2, $resolutions[0]->candidates);
    }

    #[TestDox('resolves a reference via the single complete loader when no provider is available')]
    public function testReferenceResolvesViaCompleteLoader(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('category', CategoryEntity::class, required: true)->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderMap(
                ['category_fixed' => [new LoaderTypeCapability(CategoryEntity::class)]],
                ['category_fixed' => new LoaderConfigSpecification([])],
            ),
            $this->serializersDecoding(succeeds: true),
        );

        static::assertNotNull($resolutions[0]->resolved);
        static::assertSame(CandidateOrigin::Loader, $resolutions[0]->resolved->origin);
        static::assertSame('category_fixed', $resolutions[0]->resolved->loaderSource);
        static::assertTrue($resolutions[0]->resolved->configComplete);
    }

    #[TestDox('leaves a reference unresolved with an incomplete candidate when its only loader has required config keys')]
    public function testReferenceWithIncompleteLoaderConfig(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('product', SalesChannelProductEntity::class, required: true)->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderMap(
                ['entity' => [new LoaderTypeCapability(SalesChannelProductEntity::class, ['entity' => 'product'])]],
                ['entity' => LoaderConfigSpecificationFixture::entityProperty()],
            ),
        );

        static::assertNull($resolutions[0]->resolved);
        static::assertCount(1, $resolutions[0]->candidates);
        static::assertSame(CandidateOrigin::Loader, $resolutions[0]->candidates[0]->origin);
        static::assertFalse($resolutions[0]->candidates[0]->configComplete);
        static::assertSame(['entity' => 'product'], $resolutions[0]->candidates[0]->configTemplate);
    }

    #[TestDox('leaves a reference unresolved with an incomplete candidate when its only loader\'s config fails to decode as a client defect')]
    public function testReferenceWithLoaderConfigDecodeFailureIsIncomplete(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('category', CategoryEntity::class, required: true)->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderMap(
                ['category_fixed' => [new LoaderTypeCapability(CategoryEntity::class)]],
                ['category_fixed' => new LoaderConfigSpecification([])],
            ),
            $this->serializersDecoding(succeeds: false),
        );

        static::assertNull($resolutions[0]->resolved);
        static::assertCount(1, $resolutions[0]->candidates);
        static::assertSame(CandidateOrigin::Loader, $resolutions[0]->candidates[0]->origin);
        static::assertFalse($resolutions[0]->candidates[0]->configComplete);
    }

    #[TestDox('leaves a reference unresolved and lists every candidate when multiple complete loaders match')]
    public function testReferenceWithMultipleSourcesIsAmbiguous(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('category', CategoryEntity::class, required: true)->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderMap(
                [
                    'category_a' => [new LoaderTypeCapability(CategoryEntity::class)],
                    'category_b' => [new LoaderTypeCapability(CategoryEntity::class)],
                ],
                [
                    'category_a' => new LoaderConfigSpecification([]),
                    'category_b' => new LoaderConfigSpecification([]),
                ],
            ),
            $this->serializersDecoding(succeeds: true),
        );

        static::assertNull($resolutions[0]->resolved);
        static::assertCount(2, $resolutions[0]->candidates);
    }

    #[TestDox('leaves a reference unresolved with no candidates when neither provider nor loader matches')]
    public function testReferenceWithNoSource(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('product', ProductEntity::class, required: true)->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderMap([], []),
        );

        static::assertNull($resolutions[0]->resolved);
        static::assertSame([], $resolutions[0]->candidates);
    }

    #[TestDox('resolves a required reference to the applied Stored candidate over a deterministic environment default, leaving the environment candidates list unchanged')]
    public function testAppliedStoredWiringTakesPrecedenceOverEnvironmentDefault(): void
    {
        $available = [new ProvidedContext(
            contextKey: 'product',
            fqcn: SalesChannelProductEntity::class,
            contextType: ContextType::Single,
            providerElementId: 'root-1',
            distribution: DistributionStrategy::Broadcast,
        )];

        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willReturn(SalesChannelProductEntity::class);

        $resolver = $this->appliedWiringResolver($loader);

        $resolutions = $resolver->resolve($element, new ResolutionContext('el-1', $available));

        static::assertNotNull($resolutions[0]->resolved);
        static::assertSame(CandidateOrigin::Stored, $resolutions[0]->resolved->origin);
        static::assertCount(1, $resolutions[0]->candidates);
        static::assertSame(CandidateOrigin::Parent, $resolutions[0]->candidates[0]->origin);
        static::assertSame([], array_values(array_filter(
            $resolutions[0]->candidates,
            static fn (ResolutionCandidate $candidate): bool => $candidate->origin === CandidateOrigin::Stored,
        )));
    }

    #[TestDox('falls back to the environment default when applied wiring produces a type not assignable to the declared reference')]
    public function testTypeMismatchedAppliedWiringFallsBackToEnvironmentDefault(): void
    {
        $available = [new ProvidedContext(
            contextKey: 'product',
            fqcn: SalesChannelProductEntity::class,
            contextType: ContextType::Single,
            providerElementId: 'root-1',
            distribution: DistributionStrategy::Broadcast,
        )];

        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willReturn(CategoryEntity::class);

        $resolver = $this->appliedWiringResolver($loader);

        $resolutions = $resolver->resolve($element, new ResolutionContext('el-1', $available));

        static::assertNotNull($resolutions[0]->resolved);
        static::assertSame(CandidateOrigin::Parent, $resolutions[0]->resolved->origin);
    }

    #[TestDox('yields no resolutions for an unregistered element type, leaving the defect to the diagnostics layer')]
    public function testUnregisteredTypeYieldsNoResolutions(): void
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(false);

        $resolver = new ElementResolver(
            $registry,
            $this->typeResolver(new ContentSystemDataLoaderMap([], [])),
            static::createStub(DataLoaderConfigSerializerProvider::class),
            static::createStub(DataLoaderProvider::class),
        );

        static::assertSame([], $resolver->resolve('Sw:Unknown', new ResolutionContext('el-1', [])));
    }

    #[TestDox('yields no Stored resolution when applied wiring resolution throws a client-defect exception')]
    public function testClientDefectDuringAppliedWiringYieldsNoStoredResolution(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willThrowException(ContentSystemException::configSerializerNotRegistered('entity'));

        $resolver = $this->appliedWiringResolver($loader);

        $resolutions = $resolver->resolve($element, new ResolutionContext('el-1', []));

        static::assertNull($resolutions[0]->resolved);
    }

    #[TestDox('propagates a non-client-defect exception raised while resolving applied wiring\'s produced type')]
    public function testNonClientDefectDuringAppliedWiringPropagates(): void
    {
        $element = StoredElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $exception = ContentSystemException::mutationTargetNotFound('el-1');

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willThrowException($exception);

        $resolver = $this->appliedWiringResolver($loader);

        $this->expectExceptionObject($exception);

        $resolver->resolve($element, new ResolutionContext('el-1', []));
    }

    private function provided(bool $root, ?string $providerElementId = null, string $contextKey = 'product'): ProvidedContext
    {
        return new ProvidedContext(
            contextKey: $contextKey,
            fqcn: SalesChannelProductEntity::class,
            contextType: ContextType::Single,
            providerElementId: $providerElementId,
            distribution: DistributionStrategy::Broadcast,
            root: $root,
        );
    }

    /**
     * @return list<PropertyResolution>
     */
    private function resolve(
        ContentSystemElementTypeSpecification $spec,
        ResolutionContext $context,
        ContentSystemDataLoaderMap $map,
        ?DataLoaderConfigSerializerProvider $serializers = null,
    ): array {
        $resolver = new ElementResolver(
            $this->registryReturning($spec),
            $this->typeResolver($map),
            $serializers ?? static::createStub(DataLoaderConfigSerializerProvider::class),
            static::createStub(DataLoaderProvider::class),
        );

        return $resolver->resolve('Sw:Block', $context);
    }

    private function registryReturning(ContentSystemElementTypeSpecification $spec): AbstractContentSystemElementTypeRegistry
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('get')->willReturn($spec);

        return $registry;
    }

    private function typeResolver(ContentSystemDataLoaderMap $map): AbstractContentSystemDataLoaderMapResolver
    {
        $resolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $resolver->method('resolve')->willReturn($map);

        return $resolver;
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

    /**
     * @param AbstractContentDataLoader<Struct> $loader
     */
    private function appliedWiringResolver(AbstractContentDataLoader $loader): ElementResolver
    {
        return new ElementResolver(
            $this->registryReturning(ContentSystemElementTypeSpecificationBuilder::create()->reference('product', SalesChannelProductEntity::class, required: true)->build()),
            $this->typeResolver(new ContentSystemDataLoaderMap([], [])),
            static::createStub(DataLoaderConfigSerializerProvider::class),
            $this->loaderProvider($loader),
        );
    }

    private function serializersDecoding(bool $succeeds): DataLoaderConfigSerializerProvider
    {
        $serializers = static::createStub(DataLoaderConfigSerializerProvider::class);

        if ($succeeds) {
            $serializers->method('decode')->willReturn(static::createStub(AbstractContentDataLoaderConfig::class));

            return $serializers;
        }

        $serializers->method('decode')->willThrowException(ContentSystemException::configSerializerNotRegistered('x'));

        return $serializers;
    }
}
