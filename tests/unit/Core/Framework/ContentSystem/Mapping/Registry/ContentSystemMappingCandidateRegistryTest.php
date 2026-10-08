<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
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

    public function testTheFirstProviderToOfferAPathKeepsIt(): void
    {
        $specific = self::candidate('category.name', label: 'the specific one');
        $crossCutting = self::candidate('category.name', label: 'the overlapping one');

        $registry = new ContentSystemMappingCandidateRegistry([
            new StaticMappingCandidateProvider(['category'], [$specific]),
            new StaticMappingCandidateProvider(['category'], [$crossCutting]),
        ]);

        static::assertSame($specific, $registry->forRootSource('category')['category.name']);
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
            null,
            null,
        );

        static::assertSame($original, $registry->forRootSource('product')['plugin:example']);
        $provider->candidates = [$updated];

        static::assertSame($original, $registry->forRootSource('product')['plugin:example']);
        static::assertSame('resolved', $registry->resolveSourceForRootSource('product', $source, $context));
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
            null,
            null,
        );
        $registry = new ContentSystemMappingCandidateRegistry([
            new SourceResolvingProvider(['product'], [], 'broad provider'),
            new SourceResolvingProvider(['product'], [$candidate], 'candidate provider'),
        ]);
        $decorator = new ForwardingMappingCandidateRegistry($registry);

        static::assertSame('candidate provider', $decorator->resolveSourceForRootSource('product', $source, $context));
        static::assertNull($decorator->resolveSourceForRootSource('category', $source, $context));
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

    private static function candidate(
        string $path,
        string $label = 'a label',
        ?MappingSourceReference $source = null,
    ): MappingCandidate {
        return new MappingCandidate(
            path: $path,
            label: $label,
            description: 'a description',
            group: 'basic',
            valueType: 'string',
            source: $source,
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
    public function __construct(array $rootSources, array $candidates, private readonly string $result)
    {
        parent::__construct($rootSources, $candidates);
    }

    public function supportsSource(MappingSourceReference $source): bool
    {
        return $source->type === 'plugin' && $source->id === 'example';
    }

    public function resolveSource(MappingSourceReference $source, MappingSourceResolutionContext $context): mixed
    {
        return $this->result;
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
    ): mixed {
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

    public function resolveSource(MappingSourceReference $source, MappingSourceResolutionContext $context): mixed
    {
        return 'resolved';
    }
}
