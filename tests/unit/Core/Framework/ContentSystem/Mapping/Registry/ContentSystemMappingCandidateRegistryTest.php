<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
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

    public function testRejectsDuplicateCandidateKeysAcrossProviders(): void
    {
        $this->expectExceptionObject(ContentSystemException::duplicateMappingCandidate('category.name'));

        (new ContentSystemMappingCandidateRegistry([
            new StaticMappingCandidateProvider(['category'], [self::candidate('category.name')]),
            new StaticMappingCandidateProvider(['category'], [self::candidate('category.name', label: 'duplicate')]),
        ]))->forRootSource('category');
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
        static::assertSame('resolved', $registry->resolveSourceForRootSource('product', $source, $context)->value);
        static::assertSame(1, $provider->provideCalls);

        $registry->reset();

        static::assertSame($updated, $registry->forRootSource('product')['plugin:example']);
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
        ]);
        $decorator = new ForwardingMappingCandidateRegistry($registry);

        static::assertSame('candidate provider', $decorator->resolveSourceForRootSource('product', $source, $context)->value);
        static::assertFalse($decorator->resolveSourceForRootSource('category', $source, $context)->hasValue);
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
            'product',
        );
        $registry = new ContentSystemMappingCandidateRegistry([
            new SourceResolvingProvider(['product'], [$candidate], 'resolved'),
            new StaticMappingCandidateProvider(['product'], [self::candidate('product.name')]),
        ]);

        $registry->resolveSource($source, $context);

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

        static::assertSame('owning provider', $registry->resolveSourceForRootSource('product', $source, $context)->value);
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
            static function (MappingSourceReference $reference, MappingSourceResolutionContext $forwarded) use ($source, $element, $rootValues, $loaderValues, $cacheContext): MappingSourceResolution {
                static::assertSame($source, $reference);
                static::assertSame($element, $forwarded->element);
                static::assertSame($rootValues, $forwarded->rootValues);
                static::assertSame($loaderValues, $forwarded->loaderValues);
                static::assertSame($cacheContext, $forwarded->cacheContext);
                static::assertSame('product', $forwarded->rootSource);
                $forwarded->cacheContext->addTags(['provider-data']);

                return MappingSourceResolution::resolved($forwarded->loaderValues['selectedName']);
            },
        );
        $registry = new ForwardingMappingCandidateRegistry(new ContentSystemMappingCandidateRegistry([$provider]));

        static::assertSame('Shirt', $registry->resolveSourceForRootSource('product', $source, $context)->value);
        static::assertSame(['provider-data'], $cacheContext->getTags());
    }

    public function testSourceResolutionWithoutARootSourceDoesNotBypassTheCatalogue(): void
    {
        $source = new MappingSourceReference('plugin', 'example');
        $provider = new SourceResolvingProvider(['product'], [self::candidate('plugin:example', source: $source)], 'resolved');
        $registry = new ContentSystemMappingCandidateRegistry([$provider]);
        $context = new MappingSourceResolutionContext(
            new StoredElement('element-id', 'test-element'),
            [],
            [],
            null,
        );

        static::assertFalse($registry->resolveSource($source, $context)->hasValue);
    }

    public function testResolutionKeepsPresentNullDistinctFromNoValue(): void
    {
        $source = new MappingSourceReference('plugin', 'example');
        $provider = new SourceResolvingProvider(['product'], [self::candidate('plugin:example', source: $source)], null);
        $registry = new ContentSystemMappingCandidateRegistry([$provider]);
        $context = new MappingSourceResolutionContext(new StoredElement('element-id', 'test-element'), [], [], null);

        $result = $registry->resolveSourceForRootSource('product', $source, $context);

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

        $registry->resolveSourceForRootSource('product', $source, $context);
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

        $registry->resolveSourceForRootSource('product', $source, $context);
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

        $registry->resolveSourceForRootSource('product', $source, $context);
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
        MappingSourceReference $source,
        MappingSourceResolutionContext $context,
    ): MappingSourceResolution {
        return $this->decorated->resolveSource($source, $context);
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
