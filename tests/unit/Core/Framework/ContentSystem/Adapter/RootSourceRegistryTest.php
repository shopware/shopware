<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
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
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[CoversClass(RootSourceRegistry::class)]
class RootSourceRegistryTest extends TestCase
{
    #[TestDox('knownRootSources unions entity types, section keys and none, and excludes main')]
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

    #[TestDox('entityRootSources returns only the entity-type subset')]
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
