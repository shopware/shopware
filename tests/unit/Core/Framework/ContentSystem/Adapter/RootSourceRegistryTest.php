<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Shopware\Core\Framework\ContentSystem\Adapter\NoneSpecificationSource;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RootSourceRegistry::class)]
class RootSourceRegistryTest extends TestCase
{
    #[TestDox('returns the union of entity types, section keys and none, but never main')]
    public function testKnownRootSourcesUnionsEverythingButMain(): void
    {
        $registry = new RootSourceRegistry(
            entityTypes: ['product', 'category', 'landing_page'],
            sectionSources: $this->sectionLocator(['header', 'footer']),
            noneSource: new NoneSpecificationSource(),
            entitySources: [],
        );

        $known = $registry->knownRootSources();

        static::assertEqualsCanonicalizing(
            ['product', 'category', 'landing_page', 'header', 'footer', 'none'],
            $known,
        );
        static::assertNotContains('main', $known);
    }

    #[TestDox('returns only the entity-type subset of the known root sources')]
    public function testEntityRootSourcesIsTheEntitySubset(): void
    {
        $registry = new RootSourceRegistry(
            entityTypes: ['product', 'category'],
            sectionSources: $this->sectionLocator(['header', 'footer']),
            noneSource: new NoneSpecificationSource(),
            entitySources: [],
        );

        static::assertSame(['product', 'category'], $registry->entityRootSources());
    }

    #[TestDox('resolve returns the entity source root-ambient context for an entity type')]
    public function testResolveReturnsEntitySourceContext(): void
    {
        $rootContext = [$this->providedContext('product')];

        $registry = new RootSourceRegistry(
            entityTypes: ['product'],
            sectionSources: $this->sectionLocator([]),
            noneSource: new NoneSpecificationSource(),
            entitySources: [$this->entitySource('product', $rootContext)],
        );

        static::assertSame($rootContext, $registry->resolve('product', Context::createDefaultContext()));
    }

    #[TestDox('resolve returns an empty list for a section source')]
    public function testResolveReturnsEmptyForSection(): void
    {
        $registry = new RootSourceRegistry(
            entityTypes: [],
            sectionSources: $this->sectionLocator(['header']),
            noneSource: new NoneSpecificationSource(),
            entitySources: [],
        );

        static::assertSame([], $registry->resolve('header', Context::createDefaultContext()));
    }

    #[TestDox('resolve returns an empty list for none')]
    public function testResolveReturnsEmptyForNone(): void
    {
        $registry = new RootSourceRegistry(
            entityTypes: ['product'],
            sectionSources: $this->sectionLocator(['header']),
            noneSource: new NoneSpecificationSource(),
            entitySources: [$this->entitySource('product', [$this->providedContext('product')])],
        );

        static::assertSame([], $registry->resolve('none', Context::createDefaultContext()));
    }

    #[TestDox('resolve fails hard with a 500 on an id outside the known set')]
    public function testResolveFailsHardOnUnknownId(): void
    {
        $registry = new RootSourceRegistry(
            entityTypes: ['product'],
            sectionSources: $this->sectionLocator(['header']),
            noneSource: new NoneSpecificationSource(),
            entitySources: [$this->entitySource('product', [])],
        );

        $this->expectExceptionObject(ContentSystemException::rootSourceResolutionUnsupported('main'));

        $registry->resolve('main', Context::createDefaultContext());
    }

    #[DataProvider('returnsNullForBlankRootSourceProvider')]
    #[TestDox('resolveGated returns null for $_dataName without gating membership')]
    public function testResolveGatedReturnsNullForBlankRootSource(?string $rootSource): void
    {
        $registry = new RootSourceRegistry(
            entityTypes: ['product'],
            sectionSources: $this->sectionLocator(['header']),
            noneSource: new NoneSpecificationSource(),
            entitySources: [$this->entitySource('product', [$this->providedContext('product')])],
        );

        static::assertNull($registry->resolveGated($rootSource, Context::createDefaultContext()));
    }

    #[TestDox('resolveGated resolves a member root source to its root-ambient context')]
    public function testResolveGatedResolvesMemberRootSource(): void
    {
        $rootContext = [$this->providedContext('product')];

        $registry = new RootSourceRegistry(
            entityTypes: ['product'],
            sectionSources: $this->sectionLocator(['header']),
            noneSource: new NoneSpecificationSource(),
            entitySources: [$this->entitySource('product', $rootContext)],
        );

        static::assertSame($rootContext, $registry->resolveGated('product', Context::createDefaultContext()));
    }

    #[TestDox('resolveGated rejects a non-member root source with the unknownRootSource 400 before resolving')]
    public function testResolveGatedRejectsNonMemberRootSource(): void
    {
        $registry = new RootSourceRegistry(
            entityTypes: ['product'],
            sectionSources: $this->sectionLocator(['header']),
            noneSource: new NoneSpecificationSource(),
            entitySources: [$this->entitySource('product', [])],
        );

        $this->expectExceptionObject(ContentSystemException::unknownRootSource('definitely-not-a-root-source'));

        $registry->resolveGated('definitely-not-a-root-source', Context::createDefaultContext());
    }

    #[TestDox('sourceFor returns the registered section source for a section id')]
    public function testSourceForSectionReturnsTheRegisteredSectionSource(): void
    {
        $sectionSource = static::createStub(AbstractSpecificationSource::class);

        $registry = new RootSourceRegistry(
            entityTypes: [],
            sectionSources: new ServiceLocator(['header' => fn (): AbstractSpecificationSource => $sectionSource]),
            noneSource: new NoneSpecificationSource(),
            entitySources: [],
        );

        static::assertSame($sectionSource, $registry->sourceFor('header'));
    }

    #[TestDox('sourceFor fails hard when an entity type is declared but no source claims it')]
    public function testSourceForEntityTypeWithoutMatchingSourceFailsHard(): void
    {
        $registry = new RootSourceRegistry(
            entityTypes: ['custom'],
            sectionSources: $this->sectionLocator([]),
            noneSource: new NoneSpecificationSource(),
            entitySources: [$this->entitySource('product', [])],
        );

        $this->expectExceptionObject(ContentSystemException::rootSourceResolutionUnsupported('custom'));

        $registry->sourceFor('custom');
    }

    #[TestDox('de-duplicates an entity type baked by two sources in both known-set accessors')]
    public function testDeduplicatesRepeatedEntityTypeInKnownSetAccessors(): void
    {
        $registry = new RootSourceRegistry(
            entityTypes: ['product', 'product', 'category'],
            sectionSources: $this->sectionLocator(['header']),
            noneSource: new NoneSpecificationSource(),
            entitySources: [],
        );

        static::assertSame(['product', 'category', 'header', 'none'], $registry->knownRootSources());
        static::assertSame(['product', 'category'], $registry->entityRootSources());
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function returnsNullForBlankRootSourceProvider(): iterable
    {
        yield 'a null root source' => [null];
        yield 'an empty-string root source' => [''];
    }

    /**
     * @param list<ProvidedContext> $rootContext
     */
    private function entitySource(string $entityType, array $rootContext): AbstractSpecificationSource
    {
        $source = static::createStub(AbstractSpecificationSource::class);
        $source->method('supportsEntityType')->willReturnCallback(static fn (string $type): bool => $type === $entityType);
        $source->method('providedRootContext')->willReturn($rootContext);

        return $source;
    }

    /**
     * @param list<string> $sections
     *
     * @return ServiceLocator<AbstractSpecificationSource>
     */
    private function sectionLocator(array $sections): ServiceLocator
    {
        $factories = [];
        foreach ($sections as $section) {
            $factories[$section] = fn (): AbstractSpecificationSource => static::createStub(AbstractSpecificationSource::class);
        }

        return new ServiceLocator($factories);
    }

    private function providedContext(string $key): ProvidedContext
    {
        return new ProvidedContext(
            contextKey: $key,
            fqcn: \stdClass::class,
            contextType: ContextType::Single,
            providerElementId: null,
            distribution: DistributionStrategy::Broadcast,
        );
    }
}
