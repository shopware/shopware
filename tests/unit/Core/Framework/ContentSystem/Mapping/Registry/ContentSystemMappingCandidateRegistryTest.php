<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\ContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemMappingCandidateRegistry::class)]
class ContentSystemMappingCandidateRegistryTest extends TestCase
{
    /**
     * @param list<bool> $declaredProviders
     */
    #[DataProvider('collisionOrders')]
    public function testCollisionsExcludeEveryOwnerWithoutAffectingOtherCandidates(array $declaredProviders): void
    {
        $providers = [];
        $available = [];
        foreach ($declaredProviders as $index => $declared) {
            $provider = $this->createMock($declared ? DeclaredMappingCandidateProvider::class : AbstractMappingCandidateProvider::class);
            $provider->method('supports')->willReturn(true);
            $path = 'category.available' . $index;
            $available[] = $path;
            $provider->method('provide')->willReturn([self::candidate('category.name'), self::candidate($path)]);
            $provider->expects($this->never())->method('resolveRootSource');
            $providers[] = $provider;
        }
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(
            'Ambiguous mapping candidates were excluded from the catalogue.',
            static::callback(static fn (array $context): bool => $context['rootSource'] === 'category'
                && $context['candidate'] === 'category.name'
                && \count($context['providers']) === 2),
        );
        $registry = new ContentSystemMappingCandidateRegistry($providers, $logger);

        static::assertSame($available, array_keys($registry->forRootSource('category')));
        static::assertTrue($registry->resolveSource(
            'category',
            MappingSourceReference::root('category', 'name'),
            new MappingSourceResolutionContext(new StoredElement('element-1', 'test-element'), [], [], null),
        )->isNoValue());
        static::assertSame($available, array_keys($registry->forRootSource('category')));
    }

    /**
     * @return iterable<string, array{list<bool>}>
     */
    public static function collisionOrders(): iterable
    {
        yield 'declared then dynamic' => [[true, false]];
        yield 'dynamic then declared' => [[false, true]];
        yield 'two dynamic providers' => [[false, false]];
        yield 'a third provider cannot restore the name' => [[true, false, false]];
        yield 'declared provider last cannot restore the name' => [[false, false, true]];
    }

    public function testDifferentConfigurationsWithTheSameDisplayNameAreAlsoExcluded(): void
    {
        $first = new MappingSourceReference('AcmePlugin', 'message', ['mode' => 'first'], 'text');
        $second = new MappingSourceReference('AcmePlugin', 'message', ['mode' => 'second'], 'text');
        $registry = new ContentSystemMappingCandidateRegistry([
            new StaticMappingCandidateProvider(['product'], [self::candidate($first->displayName(), source: $first)]),
            new StaticMappingCandidateProvider(['product'], [self::candidate($second->displayName(), source: $second)]),
        ]);

        static::assertSame([], $registry->forRootSource('product'));
    }

    public function testDuplicatesWithinOneProviderAreExcluded(): void
    {
        $registry = new ContentSystemMappingCandidateRegistry([
            new StaticMappingCandidateProvider(['product'], [self::candidate('product.name'), self::candidate('product.name')]),
        ]);

        static::assertSame([], $registry->forRootSource('product'));
    }

    public function testResetAllowsAPreviouslyAmbiguousNameAfterTheConflictIsRemoved(): void
    {
        $source = new MappingSourceReference('plugin', 'example');
        $candidate = self::candidate($source->displayName(), source: $source);
        $provider = new MutableMappingCandidateProvider([$candidate, $candidate]);
        $registry = new ContentSystemMappingCandidateRegistry([$provider]);
        static::assertSame([], $registry->forRootSource('product'));

        $provider->candidates = [$candidate];
        $registry->reset();

        static::assertSame([$source->displayName() => $candidate], $registry->forRootSource('product'));
        static::assertSame('resolved', $registry->resolveSource(
            'product',
            $source,
            new MappingSourceResolutionContext(new StoredElement('element-1', 'test-element'), [], [], null),
        )->value);
    }

    public function testBundleNamespacesKeepTheSameLocalSourceNameDistinct(): void
    {
        $providers = [];
        $sources = [];
        foreach (['AcmePlugin', 'OtherPlugin'] as $bundleName) {
            $source = new MappingSourceReference($bundleName, 'recommendations', path: 'title');
            $sources[] = $source;
            $provider = $this->createMock(AbstractMappingCandidateProvider::class);
            $provider->method('supports')->willReturn(true);
            $provider->method('provide')->willReturn([self::candidate($source->displayName(), source: $source)]);
            $provider->method('supportsSource')->willReturn(true);
            $provider->expects($this->once())->method('resolveSource')->with($source)->willReturn(MappingSourceResolution::resolved($bundleName));
            $providers[] = $provider;
        }
        $registry = new ContentSystemMappingCandidateRegistry($providers);
        static::assertSame(['AcmePlugin:recommendations.title', 'OtherPlugin:recommendations.title'], array_keys($registry->forRootSource('product')));

        foreach ($sources as $source) {
            static::assertSame($source->type, $registry->resolveSource(
                'product',
                $source,
                new MappingSourceResolutionContext(new StoredElement('element-1', 'test-element'), [], [], null),
            )->value);
        }
    }

    public function testAggregatesOnlyTheProvidersThatClaimTheRootSource(): void
    {
        $registry = new ContentSystemMappingCandidateRegistry([
            new StaticMappingCandidateProvider(['category'], [self::candidate('category.name')]),
            new StaticMappingCandidateProvider(['product'], [self::candidate('product.name')]),
        ]);

        static::assertSame(['category.name'], array_keys($registry->forRootSource('category')));
    }

    public function testKeysTheCatalogueByPathSoTheWriteGateCanLookOneUp(): void
    {
        $candidate = self::candidate('category.name');

        $registry = new ContentSystemMappingCandidateRegistry([
            new StaticMappingCandidateProvider(['category'], [$candidate]),
        ]);

        static::assertSame($candidate, $registry->forRootSource('category')['category.name']);
    }

    public function testMergesEveryClaimingProviderIntoOneCatalogue(): void
    {
        $registry = new ContentSystemMappingCandidateRegistry([
            new StaticMappingCandidateProvider(['category'], [self::candidate('category.name')]),
            new StaticMappingCandidateProvider(['category', 'product'], [self::candidate('category.customField')]),
        ]);

        static::assertSame(
            ['category.name', 'category.customField'],
            array_keys($registry->forRootSource('category'))
        );
    }

    public function testCachesTheCatalogueUntilResetThenReadsUpdatedProviderData(): void
    {
        $source = new MappingSourceReference('plugin', 'example');
        $original = self::candidate('plugin:example', label: 'original', source: $source);
        $updated = self::candidate('plugin:example', label: 'updated', source: $source);
        $provider = new MutableMappingCandidateProvider([$original]);
        $registry = new ContentSystemMappingCandidateRegistry([$provider]);
        $context = new MappingSourceResolutionContext(
            new StoredElement('element-id', 'test-element'),
            [],
            [],
            null,
        );

        static::assertSame($original, $registry->forRootSource('product')['plugin:example']);
        $provider->candidates = [$updated];

        static::assertSame($original, $registry->forRootSource('product')['plugin:example']);
        static::assertSame('resolved', $registry->resolveSource('product', $source, $context)->value);
        static::assertSame(1, $provider->provideCalls);

        $registry->reset();

        static::assertSame($updated, $registry->forRootSource('product')['plugin:example']);
        static::assertSame(2, $provider->provideCalls);
    }

    public function testForwardingDecoratorResetsItsInnerRequestCatalogue(): void
    {
        $source = new MappingSourceReference('plugin', 'example');
        $provider = new MutableMappingCandidateProvider([self::candidate($source->displayName(), source: $source)]);
        $decorator = new ForwardingMappingCandidateRegistry(new ContentSystemMappingCandidateRegistry([$provider]));
        $decorator->forRootSource('product');
        $provider->candidates = [];

        $decorator->reset();

        static::assertSame([], $decorator->forRootSource('product'));
        static::assertSame(2, $provider->provideCalls);
    }

    public function testSourceResolutionUsesTheProviderThatOffersTheCandidateForTheRootSource(): void
    {
        $source = new MappingSourceReference('plugin', 'example');
        $candidate = self::candidate('plugin:example', source: $source);
        $context = new MappingSourceResolutionContext(
            new StoredElement('element-id', 'test-element'),
            [],
            [],
            null,
        );
        $registry = new ContentSystemMappingCandidateRegistry([
            new SourceResolvingProvider(['product'], [], 'broad provider'),
            new SourceResolvingProvider(['product'], [$candidate], 'candidate provider'),
            new SourceResolvingProvider(['category'], [$candidate], 'category provider'),
        ]);
        $decorator = new ForwardingMappingCandidateRegistry($registry);

        static::assertSame('candidate provider', $decorator->resolveSource('product', $source, $context)->value);
        static::assertSame('category provider', $decorator->resolveSource('category', $source, $context)->value);
        static::assertFalse($decorator->resolveSource('landing_page', $source, $context)->hasValue);
    }

    public function testCollectsOnlyTheConsultedCandidatesCacheTags(): void
    {
        $source = new MappingSourceReference('plugin', 'example');
        $candidate = self::candidate('plugin:example', source: $source, cacheTags: ['candidate-tag', 'set-tag']);
        $cacheContext = new RenderingCacheContext();
        $context = new MappingSourceResolutionContext(
            new StoredElement('element-id', 'test-element'),
            [],
            [],
            $cacheContext,
        );
        $registry = new ContentSystemMappingCandidateRegistry([
            new SourceResolvingProvider(['product'], [$candidate], 'resolved'),
            new StaticMappingCandidateProvider(['product'], [self::candidate('product.name')]),
        ]);

        $registry->resolveSource('product', $source, $context);

        static::assertSame(['candidate-tag', 'set-tag'], $cacheContext->getTags());
    }

    public function testRootResolutionUsesTheProviderThatOfferedTheExactCandidate(): void
    {
        $source = MappingSourceReference::root('product', 'name');
        $candidate = self::candidate('product.name', source: $source);
        $registry = new ContentSystemMappingCandidateRegistry([
            new SourceResolvingProvider(['product'], [], 'wrong provider'),
            new SourceResolvingProvider(['product'], [$candidate], 'owning provider'),
        ]);
        $context = new MappingSourceResolutionContext(new StoredElement('element-id', 'test-element'), [], [], null);

        static::assertSame('owning provider', $registry->resolveSource('product', $source, $context)->value);
    }

    public function testSameDisplayNameCannotBypassTheExactSourceConfigurationCheck(): void
    {
        $approved = new MappingSourceReference('plugin', 'example', ['mode' => 'approved'], 'value');
        $unlisted = new MappingSourceReference('plugin', 'example', ['mode' => 'unlisted'], 'value');
        $provider = static::createMock(AbstractMappingCandidateProvider::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('provide')->willReturn([self::candidate('plugin:example.value', source: $approved)]);
        $provider->expects($this->never())->method('resolveSource');
        $registry = new ContentSystemMappingCandidateRegistry([$provider]);
        $context = new MappingSourceResolutionContext(new StoredElement('element-id', 'test-element'), [], [], null);

        static::assertTrue($registry->resolveSource('product', $unlisted, $context)->isNoValue());
    }

    public function testForwardingDecoratorPreservesProviderInputsAndTheLiveCacheContext(): void
    {
        $source = MappingSourceReference::root('product', 'name');
        $element = new StoredElement('element-id', 'test-element');
        $rootValues = ['product' => 'product-data'];
        $loaderValues = ['selectedName' => 'Shirt'];
        $cacheContext = new RenderingCacheContext();
        $context = new MappingSourceResolutionContext($element, $rootValues, $loaderValues, $cacheContext);
        $provider = static::createMock(AbstractMappingCandidateProvider::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('provide')->willReturn([self::candidate('product.name', source: $source)]);
        $provider->expects($this->once())->method('resolveRootSource')->willReturnCallback(
            static function (MappingSourceReference $reference, MappingSourceResolutionContext $forwarded) use ($source, $element, $rootValues, $loaderValues, $cacheContext, $context): MappingSourceResolution {
                static::assertSame($source, $reference);
                static::assertSame($context, $forwarded);
                static::assertSame($element, $forwarded->element);
                static::assertSame($rootValues, $forwarded->rootValues);
                static::assertSame($loaderValues, $forwarded->loaderValues);
                static::assertSame($cacheContext, $forwarded->cacheContext);
                $forwarded->cacheContext->addTags(['provider-data']);

                return MappingSourceResolution::resolved($forwarded->loaderValues['selectedName']);
            },
        );
        $registry = new ForwardingMappingCandidateRegistry(new ContentSystemMappingCandidateRegistry([$provider]));

        static::assertSame('Shirt', $registry->resolveSource('product', $source, $context)->value);
        static::assertSame(['provider-data'], $cacheContext->getTags());
    }

    public function testResolutionKeepsPresentNullDistinctFromNoValue(): void
    {
        $source = new MappingSourceReference('plugin', 'example');
        $provider = new SourceResolvingProvider(['product'], [self::candidate('plugin:example', source: $source)], null);
        $registry = new ContentSystemMappingCandidateRegistry([$provider]);
        $context = new MappingSourceResolutionContext(new StoredElement('element-id', 'test-element'), [], [], null);

        $result = $registry->resolveSource('product', $source, $context);

        static::assertTrue($result->hasValue);
        static::assertNull($result->value);
        static::assertFalse($result->isNoValue());
    }

    public function testProviderResolutionFailurePropagates(): void
    {
        $source = new MappingSourceReference('plugin', 'example');
        $candidate = self::candidate('plugin:example', source: $source);
        $provider = new FailingMappingCandidateProvider(['product'], [$candidate]);
        $failure = new \RuntimeException('provider failure');
        $provider->failure = $failure;
        $registry = new ContentSystemMappingCandidateRegistry([$provider]);
        $context = new MappingSourceResolutionContext(new StoredElement('element-id', 'test-element'), [], [], null);

        $this->expectExceptionObject($failure);

        $registry->resolveSource('product', $source, $context);
    }

    public function testProviderSourceSupportErrorPropagates(): void
    {
        $source = new MappingSourceReference('plugin', 'example');
        $failure = new \TypeError('provider source check failed');
        $provider = static::createStub(AbstractMappingCandidateProvider::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('provide')->willReturn([self::candidate('plugin:example', source: $source)]);
        $provider->method('supportsSource')->willThrowException($failure);
        $registry = new ContentSystemMappingCandidateRegistry([$provider]);
        $context = new MappingSourceResolutionContext(new StoredElement('element-id', 'test-element'), [], [], null);

        $this->expectException(\TypeError::class);

        $registry->resolveSource('product', $source, $context);
    }

    public function testRootProviderResolutionErrorPropagates(): void
    {
        $source = MappingSourceReference::root('product', 'name');
        $failure = new \TypeError('root provider resolution failed');
        $provider = static::createStub(AbstractMappingCandidateProvider::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('provide')->willReturn([self::candidate('product.name', source: $source)]);
        $provider->method('resolveRootSource')->willThrowException($failure);
        $registry = new ContentSystemMappingCandidateRegistry([$provider]);
        $context = new MappingSourceResolutionContext(new StoredElement('element-id', 'test-element'), [], [], null);

        $this->expectException(\TypeError::class);

        $registry->resolveSource('product', $source, $context);
    }

    public function testProviderCatalogueFailurePropagates(): void
    {
        $failure = new \RuntimeException('provider catalogue failed');
        $provider = static::createStub(AbstractMappingCandidateProvider::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('provide')->willThrowException($failure);
        $registry = new ContentSystemMappingCandidateRegistry([$provider]);

        $this->expectExceptionObject($failure);

        $registry->forRootSource('product');
    }

    public function testARootSourceNobodyClaimsYieldsAnEmptyCatalogueRatherThanThrowing(): void
    {
        $registry = new ContentSystemMappingCandidateRegistry([
            new StaticMappingCandidateProvider(['category'], [self::candidate('category.name')]),
        ]);

        static::assertSame([], $registry->forRootSource('none'));
    }

    public function testGetDecoratedThrowsOnTheLeaf(): void
    {
        $registry = new ContentSystemMappingCandidateRegistry([]);

        $this->expectExceptionObject(new DecorationPatternException(ContentSystemMappingCandidateRegistry::class));

        $registry->getDecorated();
    }

    /**
     * @param list<string> $cacheTags
     */
    private static function candidate(
        string $path,
        string $label = 'a label',
        ?MappingSourceReference $source = null,
        array $cacheTags = [],
    ): MappingCandidate {
        return new MappingCandidate(
            path: $path,
            label: $label,
            description: 'a description',
            group: 'basic',
            valueType: 'string',
            source: $source,
            cacheTags: $cacheTags,
        );
    }
}

/**
 * @internal
 */
class DeclaredMappingCandidateProvider extends StaticMappingCandidateProvider
{
    public static function registeredCandidateKeys(): array
    {
        return ['category' => ['category.name']];
    }
}

/**
 * @internal
 */
class StaticMappingCandidateProvider extends AbstractMappingCandidateProvider
{
    /**
     * @param list<string> $rootSources
     * @param list<MappingCandidate> $candidates
     */
    public function __construct(
        private readonly array $rootSources,
        private readonly array $candidates,
    ) {
    }

    public static function registeredCandidateKeys(): array
    {
        return [];
    }

    public function supports(string $rootSource): bool
    {
        return \in_array($rootSource, $this->rootSources, true);
    }

    public function provide(string $rootSource): array
    {
        return $this->candidates;
    }
}

/**
 * @internal
 */
class SourceResolvingProvider extends StaticMappingCandidateProvider
{
    /**
     * @param list<string> $rootSources
     * @param list<MappingCandidate> $candidates
     */
    public function __construct(array $rootSources, array $candidates, private readonly mixed $result)
    {
        parent::__construct($rootSources, $candidates);
    }

    public function supportsSource(MappingSourceReference $source): bool
    {
        return $source->type === 'plugin' && $source->id === 'example';
    }

    public function resolveSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        return MappingSourceResolution::resolved($this->result);
    }

    public function resolveRootSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        return MappingSourceResolution::resolved($this->result);
    }
}

/**
 * @internal
 */
class ForwardingMappingCandidateRegistry extends AbstractContentSystemMappingCandidateRegistry
{
    public function __construct(private readonly AbstractContentSystemMappingCandidateRegistry $decorated)
    {
    }

    public function getDecorated(): AbstractContentSystemMappingCandidateRegistry
    {
        return $this->decorated;
    }

    public function forRootSource(string $rootSource): array
    {
        return $this->decorated->forRootSource($rootSource);
    }

    public function resolveSource(
        string $rootSource,
        MappingSourceReference $source,
        MappingSourceResolutionContext $context,
    ): MappingSourceResolution {
        return $this->decorated->resolveSource($rootSource, $source, $context);
    }
}

/**
 * @internal
 */
class MutableMappingCandidateProvider extends AbstractMappingCandidateProvider
{
    public int $provideCalls = 0;

    /**
     * @param list<MappingCandidate> $candidates
     */
    public function __construct(public array $candidates)
    {
    }

    public static function registeredCandidateKeys(): array
    {
        return [];
    }

    public function supports(string $rootSource): bool
    {
        return $rootSource === 'product';
    }

    public function provide(string $rootSource): array
    {
        ++$this->provideCalls;

        return $this->candidates;
    }

    public function supportsSource(MappingSourceReference $source): bool
    {
        return $source->type === 'plugin' && $source->id === 'example';
    }

    public function resolveSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        return MappingSourceResolution::resolved('resolved');
    }
}

/**
 * @internal
 */
class FailingMappingCandidateProvider extends StaticMappingCandidateProvider
{
    public ?\Throwable $failure = null;

    public function supportsSource(MappingSourceReference $source): bool
    {
        return $source->type === 'plugin' && $source->id === 'example';
    }

    public function resolveSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        throw $this->failure ?? new \RuntimeException('provider failed');
    }
}
