<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Output\Index\LoaderValueIdentityFactory;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueFingerprinter;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDeliveryResolver;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDistributor;
use Shopware\Core\Framework\ContentSystem\Rendering\ElementDataResolver;
use Shopware\Core\Framework\ContentSystem\Rendering\ElementLowering;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElementFactory;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedTreeFactory;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfigSerializer;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;

/**
 * The collaborator chain is real below the loader seam: a real `ElementDataResolver` over a real
 * `LoaderInputResolver`, a real `ContextDeliveryResolver` over a real `ContextDistributor`, and a real
 * `RenderedTreeFactory` over a real `RenderedElementFactory`. Only the data loader and the element type
 * registry are doubles, so what the rendered tree carries is what the three layers actually produced.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElementLowering::class)]
class ElementLoweringTest extends TestCase
{
    /**
     * An effort-negative claim: the never-expectation is the behaviour under test, not decoration. A skeleton
     * is a structural answer, so the element's declared data requirement must not reach a loader at all, and
     * the rendered properties must stay empty rather than being filled from anywhere. The two cache facts are
     * whole-value assertions because absence is the claim: a run would disable the context on an uncacheable
     * result and would leave a tag behind, so either fact appearing proves a loader ran.
     */
    #[TestDox('renders structure with empty properties and leaves the cache context untouched in skeleton mode without running any loader')]
    public function testSkeletonModeRendersStructureWithoutRunningAnyLoader(): void
    {
        $loader = $this->loader();
        $loader->expects($this->never())->method('load');
        $cacheContext = new RenderingCacheContext();

        $lowered = $this->lowering($loader)->lower(
            [$this->rootOverRequiringChild()],
            RenderingMode::SKELETON,
            static::createStub(SalesChannelContext::class),
            new Request(),
            $cacheContext
        );

        $tree = $lowered->tree;

        // A skeleton mints no properties, so it records no provenance either: there is nothing to file.
        static::assertSame([], $lowered->provenance);
        static::assertSame('root-1', $tree[0]->id);
        static::assertSame('Sw:Section', $tree[0]->component);
        static::assertSame([], $tree[0]->properties);
        static::assertSame(['main'], array_keys($tree[0]->slots));
        static::assertSame('child-1', $tree[0]->slots['main'][0]->id);
        static::assertSame('Sw:Product', $tree[0]->slots['main'][0]->component);
        static::assertSame([], $tree[0]->slots['main'][0]->properties);
        static::assertFalse($cacheContext->isDisabled());
        static::assertSame([], $cacheContext->getTags());
    }

    /**
     * The data walk descends: the requirement sits two levels below the root, so a walk that only visits the
     * roots resolves nothing and the grandchild renders an empty property map.
     */
    #[TestDox('resolves a data requirement held by an element below the roots in full mode')]
    public function testFullModeResolvesDataForAnElementBelowTheRoots(): void
    {
        $loaded = new StubStruct();
        $grandchild = StoredElementBuilder::create('Sw:Product', 'grandchild-1')
            ->withDataRequirement('product', 'entity', new StubLoaderConfig())
            ->build();
        $child = StoredElementBuilder::create('Sw:Section', 'child-1')
            ->withSlot('inner', [$grandchild])
            ->build();
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withSlot('main', [$child])
            ->build();

        $tree = $this->lower($this->loaderReturning(ContentDataLoaderResult::cached($loaded)), [$root]);

        static::assertSame(
            ['product' => $loaded],
            $tree[0]->slots['main'][0]->slots['inner'][0]->properties
        );
    }

    /**
     * The ordering claim: loading completes over the whole forest before any distribution starts. The parent
     * stores no `product` of its own, so the only thing that can reach the child is the value the parent's
     * loader produced — and it is compared by identity, so no stored value could stand in for it. Compute the
     * deliveries before the loads and the parent distributes nothing, leaving the child's map empty.
     */
    #[TestDox('delivers a parent loader resolved value to its consuming child in full mode')]
    public function testFullModeDeliversAParentLoadedValueToItsConsumingChild(): void
    {
        $loaded = new StubStruct();
        $child = StoredElementBuilder::create('Sw:Box', 'child-1')
            ->withConsumer('product', ContextType::Single)
            ->build();
        $parent = StoredElementBuilder::create('Sw:Section', 'parent-1')
            ->withDataRequirement('product', 'entity', new StubLoaderConfig())
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$child])
            ->build();

        $tree = $this->lower($this->loaderReturning(ContentDataLoaderResult::cached($loaded)), [$parent]);

        static::assertSame(['product' => $loaded], $tree[0]->slots['main'][0]->properties);
    }

    /**
     * A required consumer with a dot path pulls a nested property off the value it is delivered, which needs
     * a Struct to traverse. A provider that hands over a bare scalar leaves the path unresolvable, and a
     * required consumer must fail rather than silently deliver null.
     */
    #[TestDox('throws when a required consumer resolves a dot path into a provided value that is not a struct')]
    public function testFullModeThrowsWhenRequiredConsumerPathIsUnresolvable(): void
    {
        $child = StoredElementBuilder::create('Sw:Box', 'child-1')
            ->withConsumer('product.manufacturer', ContextType::Single, required: true)
            ->build();
        $parent = StoredElementBuilder::create('Sw:Section', 'parent-1')
            ->withProperty('product', 'T-shirt')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$child])
            ->build();

        $this->expectExceptionObject(ContentSystemException::contextPathNotResolvable(
            'product.manufacturer',
            'child-1',
            'Context data is not a Struct instance'
        ));

        $this->lower($this->loader(), [$parent]);
    }

    /**
     * @param AbstractContentDataLoader<Struct>&MockObject $loader
     * @param list<StoredElement> $forest
     *
     * @return list<RenderedElement>
     */
    private function lower(AbstractContentDataLoader&MockObject $loader, array $forest): array
    {
        return $this->lowering($loader)->lower(
            $forest,
            RenderingMode::FULL,
            static::createStub(SalesChannelContext::class),
            new Request(),
            new RenderingCacheContext()
        )->tree;
    }

    /**
     * A root over a child that declares two data requirements, so a loader run in skeleton mode would be
     * visible both as a `load()` call and as a contribution to the cache context.
     */
    private function rootOverRequiringChild(): StoredElement
    {
        $child = StoredElementBuilder::create('Sw:Product', 'child-1')
            ->withDataRequirement('product', 'entity', new StubLoaderConfig())
            ->withDataRequirement('category', 'entity', new StubLoaderConfig())
            ->build();

        return StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withSlot('main', [$child])
            ->build();
    }

    /**
     * @param AbstractContentDataLoader<Struct>&MockObject $loader
     */
    private function lowering(AbstractContentDataLoader&MockObject $loader): ElementLowering
    {
        $provider = static::createStub(DataLoaderProvider::class);
        $provider->method('get')->willReturn($loader);

        return new ElementLowering(
            new ElementDataResolver(
                $provider,
                new LoaderInputResolver(),
                new LoaderValueIdentityFactory(
                    new DataLoaderConfigSerializerProvider(new ServiceLocator([
                        'entity' => static fn (): StubLoaderConfigSerializer => new StubLoaderConfigSerializer(),
                    ])),
                    new ConfigCanonicalizer(),
                    new ValueFingerprinter(),
                ),
            ),
            new ContextDeliveryResolver(new ContextDistributor(new ContextPathResolver())),
            new RenderedTreeFactory(new RenderedElementFactory($this->typeRegistry()))
        );
    }

    /**
     * @return AbstractContentDataLoader<Struct>&MockObject
     */
    private function loaderReturning(ContentDataLoaderResult ...$results): AbstractContentDataLoader&MockObject
    {
        $loader = $this->loader();
        $loader->method('load')->willReturnOnConsecutiveCalls(...array_values($results));

        return $loader;
    }

    /**
     * @return AbstractContentDataLoader<Struct>&MockObject
     */
    private function loader(): AbstractContentDataLoader&MockObject
    {
        $loader = $this->createMock(AbstractContentDataLoader::class);
        $loader->method('configSpecification')->willReturn(new LoaderConfigSpecification([]));

        return $loader;
    }

    private function typeRegistry(): AbstractContentSystemElementTypeRegistry
    {
        $specs = [
            'Sw:Section' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Section')->build(),
            'Sw:Box' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Box')->build(),
            'Sw:Product' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Product')
                ->reference('product', StubStruct::class)
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(
            static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]
        );

        return $registry;
    }
}
