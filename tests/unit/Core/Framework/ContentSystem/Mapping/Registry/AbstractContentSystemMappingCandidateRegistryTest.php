<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AbstractContentSystemMappingCandidateRegistry::class)]
class AbstractContentSystemMappingCandidateRegistryTest extends TestCase
{
    #[DataProvider('unapprovedSources')]
    public function testAnImmediateOverrideCannotResolveAnUnapprovedSource(string $rootSource, MappingSourceReference $source): void
    {
        $candidate = new MappingCandidate('product.name', 'Name', '', 'basic', 'string', cacheTags: ['candidate-metadata']);
        $inner = $this->createPartialMock(AbstractContentSystemMappingCandidateRegistry::class, ['getDecorated', 'forRootSource', 'cacheTagsForMapping', 'reset', 'resolveAdmittedSource']);
        $inner->method('forRootSource')->willReturnCallback(static fn (string $root): array => $root === 'product' ? [$candidate->path => $candidate] : []);
        $inner->method('cacheTagsForMapping')->willReturn(['attempt-metadata']);
        $registry = new ImmediateOverrideMappingRegistry($inner, MappingSourceResolution::resolved('Private value'));
        $cache = new RenderingCacheContext();
        $context = new MappingSourceResolutionContext(new StoredElement('element', 'text'), [], [], $cache);

        $result = $registry->resolveSource($rootSource, $source, $context);

        static::assertTrue($result->isNoValue());
        static::assertSame([], $registry->calls);
        static::assertSame(['attempt-metadata'], $cache->getTags());
    }

    /**
     * @return iterable<string, array{string, MappingSourceReference}>
     */
    public static function unapprovedSources(): iterable
    {
        yield 'unlisted member' => ['product', MappingSourceReference::root('product', 'internalComment')];
        yield 'candidate unavailable for another layout root' => ['category', MappingSourceReference::root('product', 'name')];
        yield 'unlisted configuration' => ['product', new MappingSourceReference('root', 'product', ['private' => true], 'name')];
        yield 'same display name with a different source identity' => ['product', MappingSourceReference::root('product.name')];
    }

    #[DataProvider('resolutionOutcomes')]
    public function testAnApprovedOverridePreservesItsResultAndCollectsDependencies(MappingSourceResolution $expected): void
    {
        $candidate = new MappingCandidate('product.name', 'Name', '', 'basic', 'string', cacheTags: ['candidate-metadata']);
        $inner = $this->createPartialMock(AbstractContentSystemMappingCandidateRegistry::class, ['getDecorated', 'forRootSource', 'cacheTagsForMapping', 'reset', 'resolveAdmittedSource']);
        $inner->method('forRootSource')->willReturn([$candidate->path => $candidate]);
        $inner->method('cacheTagsForMapping')->willReturn(['attempt-metadata']);
        $registry = new ImmediateOverrideMappingRegistry($inner, $expected);
        $cache = new RenderingCacheContext();
        $context = new MappingSourceResolutionContext(new StoredElement('element', 'text'), [], [], $cache);

        $actual = $registry->resolveSource('product', $candidate->source, $context);

        static::assertSame($expected, $actual);
        static::assertSame([['product', $candidate->source, $context]], $registry->calls);
        static::assertSame(['attempt-metadata', 'candidate-metadata'], $cache->getTags());
    }

    /**
     * @return iterable<string, array{MappingSourceResolution}>
     */
    public static function resolutionOutcomes(): iterable
    {
        yield 'resolved value' => [MappingSourceResolution::resolved('Override')];
        yield 'explicit null' => [MappingSourceResolution::resolved(null)];
        yield 'missing data' => [MappingSourceResolution::noValue()];
    }

    public function testEquivalentPluginConfigurationReachesTheOverrideUnchanged(): void
    {
        $catalogued = new MappingSourceReference('AcmePlugin', 'recommendations', ['limit' => 5, 'options' => ['country' => 'DE', 'currency' => 'EUR']], 'title');
        $requested = new MappingSourceReference('AcmePlugin', 'recommendations', ['options' => ['currency' => 'EUR', 'country' => 'DE'], 'limit' => 5], 'title');
        $candidate = new MappingCandidate($catalogued->displayName(), 'Recommendations', '', 'plugin', 'string', source: $catalogued);
        $inner = $this->createPartialMock(AbstractContentSystemMappingCandidateRegistry::class, ['getDecorated', 'forRootSource', 'cacheTagsForMapping', 'reset', 'resolveAdmittedSource']);
        $inner->method('forRootSource')->willReturn([$candidate->path => $candidate]);
        $result = MappingSourceResolution::resolved('Plugin title');
        $registry = new ImmediateOverrideMappingRegistry($inner, $result);
        $context = new MappingSourceResolutionContext(new StoredElement('element', 'text'), [], [], new RenderingCacheContext());

        static::assertSame($result, $registry->resolveSource('product', $requested, $context));
        static::assertSame([['product', $requested, $context]], $registry->calls);
    }

    public function testOverrideErrorsPropagateAfterDependenciesAreCollected(): void
    {
        $candidate = new MappingCandidate('product.name', 'Name', '', 'basic', 'string', cacheTags: ['candidate-metadata']);
        $inner = $this->createPartialMock(AbstractContentSystemMappingCandidateRegistry::class, ['getDecorated', 'forRootSource', 'cacheTagsForMapping', 'reset', 'resolveAdmittedSource']);
        $inner->method('forRootSource')->willReturn([$candidate->path => $candidate]);
        $inner->method('cacheTagsForMapping')->willReturn(['attempt-metadata']);
        $failure = new \TypeError('Override failed');
        $registry = new ImmediateOverrideMappingRegistry($inner, MappingSourceResolution::noValue(), $failure);
        $cache = new RenderingCacheContext();
        $context = new MappingSourceResolutionContext(new StoredElement('element', 'text'), [], [], $cache);

        try {
            $registry->resolveSource('product', $candidate->source, $context);
            static::fail('The override error must propagate.');
        } catch (\TypeError $error) {
            static::assertSame($failure, $error);
        }

        static::assertSame(['attempt-metadata', 'candidate-metadata'], $cache->getTags());
    }

    public function testCatalogueOnlyDecoratorsForwardApprovedResolutionByDefault(): void
    {
        $candidate = new MappingCandidate('product.name', 'Name', '', 'basic', 'string');
        $inner = $this->createPartialMock(AbstractContentSystemMappingCandidateRegistry::class, ['getDecorated', 'forRootSource', 'cacheTagsForMapping', 'reset', 'resolveAdmittedSource']);
        $inner->method('forRootSource')->willReturn([$candidate->path => $candidate]);
        $expected = MappingSourceResolution::resolved('Inner result');
        $context = new MappingSourceResolutionContext(new StoredElement('element', 'text'), [], [], new RenderingCacheContext());
        $inner->expects($this->once())->method('resolveAdmittedSource')->with('product', $candidate->source, $context)->willReturn($expected);
        $registry = new CatalogueForwardingMappingRegistry($inner);

        static::assertSame($expected, $registry->resolveSource('product', $candidate->source, $context));
    }

    public function testCatalogueFailuresDoNotReachTheOverride(): void
    {
        $failure = new \RuntimeException('Catalogue unavailable');
        $inner = $this->createPartialMock(AbstractContentSystemMappingCandidateRegistry::class, ['getDecorated', 'forRootSource', 'cacheTagsForMapping', 'reset', 'resolveAdmittedSource']);
        $inner->method('forRootSource')->willThrowException($failure);
        $registry = new ImmediateOverrideMappingRegistry($inner, MappingSourceResolution::resolved('Override'));
        $context = new MappingSourceResolutionContext(new StoredElement('element', 'text'), [], [], new RenderingCacheContext());

        try {
            $registry->resolveSource('product', MappingSourceReference::root('product', 'name'), $context);
            static::fail('The catalogue error must propagate.');
        } catch (\RuntimeException $error) {
            static::assertSame($failure, $error);
        }

        static::assertSame([], $registry->calls);
    }
}

/**
 * @internal
 */
class CatalogueForwardingMappingRegistry extends AbstractContentSystemMappingCandidateRegistry
{
    public function __construct(private readonly AbstractContentSystemMappingCandidateRegistry $inner)
    {
    }

    public function getDecorated(): AbstractContentSystemMappingCandidateRegistry
    {
        return $this->inner;
    }

    public function forRootSource(string $rootSource): array
    {
        return $this->inner->forRootSource($rootSource);
    }
}

/**
 * Deliberately answers without delegating resolution or collecting dependencies itself.
 *
 * @internal
 */
class ImmediateOverrideMappingRegistry extends CatalogueForwardingMappingRegistry
{
    /**
     * @var list<array{string, MappingSourceReference, MappingSourceResolutionContext}>
     */
    public array $calls = [];

    public function __construct(
        AbstractContentSystemMappingCandidateRegistry $inner,
        private readonly MappingSourceResolution $result,
        private readonly ?\Throwable $failure = null,
    ) {
        parent::__construct($inner);
    }

    protected function resolveAdmittedSource(string $rootSource, MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        $this->calls[] = [$rootSource, $source, $context];
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->result;
    }
}
