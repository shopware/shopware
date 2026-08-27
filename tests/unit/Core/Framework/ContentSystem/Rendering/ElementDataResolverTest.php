<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Output\Index\LoaderValueIdentityFactory;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueFingerprinter;
use Shopware\Core\Framework\ContentSystem\Rendering\ElementDataResolver;
use Shopware\Core\Framework\ContentSystem\Rendering\ResolvedLoaderValue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;
use Shopware\Core\Test\Stub\ContentSystem\TestNavigationShapedLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\TestNavigationShapedLoaderConfigSerializer;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;

/**
 * The loader double declares one `entityName` key and one `propertyReference` key, and the input resolver is
 * the real one, so what the loader receives is what a live loader would receive — which is what makes the
 * dereference against the element's own stored properties observable rather than assumed.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElementDataResolver::class)]
class ElementDataResolverTest extends TestCase
{
    #[TestDox('calls the loader with the inputs the resolver dereferenced from the element stored properties')]
    public function testLoaderReceivesTheResolvedInputs(): void
    {
        $captured = null;
        $loader = $this->loader();
        $loader->method('load')->willReturnCallback(
            static function (LoaderInputs $inputs) use (&$captured): ContentDataLoaderResult {
                $captured = $inputs;

                return ContentDataLoaderResult::notFound();
            }
        );

        $this->resolveWith($loader, $this->elementWithRequirement('product'));

        static::assertInstanceOf(LoaderInputs::class, $captured);
        static::assertSame('product', $captured->get('entity'));
        static::assertSame('the-active-id', $captured->get('activeProperty'));
    }

    #[TestDox('carries a loaded value under its requirement key')]
    public function testLoadedValueIsCarriedUnderItsRequirementKey(): void
    {
        $data = new StubStruct();
        $loader = $this->loaderReturning(ContentDataLoaderResult::cached($data));

        $resolved = $this->resolveWith($loader, $this->elementWithRequirement('product'));

        static::assertSame(['product'], array_keys($resolved));
        static::assertSame($data, $resolved['product']->value);
    }

    /**
     * The identity is minted here because this is the only place all four of its components exist at once:
     * later stages have neither the resolved inputs nor the value as the loader returned it.
     */
    #[TestDox('mints the dedup identity of a loaded value from the requirement, its inputs and the returned value')]
    public function testResolvedValueCarriesItsDedupIdentity(): void
    {
        $data = new StubStruct();
        $loader = $this->loaderReturning(ContentDataLoaderResult::cached($data));

        $identity = $this->resolveWith($loader, $this->elementWithRequirement('product'))['product']->identity;

        static::assertSame('entity', $identity->source);
        // Taken from the value the LOADER returned, which is what lets the response index tell that value
        // apart from one a finalization listener replaced it with.
        static::assertSame((new ValueFingerprinter())->fingerprint($data), $identity->producedFingerprint);
        // Computed the same way LoaderValueIdentityFactory does (encode+canonicalize for config, resolved
        // inputs key-sorted for inputs), so these prove the hash actually reflects this resolution's
        // config/inputs rather than merely being non-empty.
        static::assertSame(
            Hasher::hash(['activeProperty' => 'activeId', 'entity' => 'product']),
            $identity->configHash
        );
        static::assertSame(
            Hasher::hash(['activeProperty' => 'the-active-id', 'entity' => 'product']),
            $identity->inputsHash
        );
    }

    #[TestDox('gives two requirements resolving different inputs different identities')]
    public function testDifferentInputsYieldDifferentIdentities(): void
    {
        $loader = $this->loaderReturning(
            ContentDataLoaderResult::cached(new StubStruct()),
            ContentDataLoaderResult::cached(new StubStruct()),
        );

        $first = $this->resolveWith($loader, $this->elementWithRequirement('product', 'the-first-active-id'))['product']->identity;
        $second = $this->resolveWith($loader, $this->elementWithRequirement('product', 'the-second-active-id'))['product']->identity;

        // Same source and same config on both resolutions, so the inputs hash is the only component that can
        // keep them apart — and it must, or two loads of different things would share one response reference.
        static::assertSame($first->configHash, $second->configHash);
        static::assertNotSame($first->inputsHash, $second->inputsHash);
    }

    /**
     * A `notFound()` writes its key rather than omitting it, which is deliberate. The rendered side reads
     * this map with `array_key_exists`, so an omitted key would render nothing at all while a present null
     * renders as the null that means "a loader ran and found nothing".
     */
    #[TestDox('carries an explicit null under the requirement key of a loader that found nothing')]
    public function testNotFoundYieldsAPresentNull(): void
    {
        $loader = $this->loaderReturning(ContentDataLoaderResult::notFound());

        $resolved = $this->resolveWith($loader, $this->elementWithRequirement('product'));

        static::assertArrayHasKey('product', $resolved);
        static::assertNull($resolved['product']->value);
    }

    #[TestDox('disables the cache context for a result that is not cache aware')]
    public function testUncacheableResultDisablesTheCacheContext(): void
    {
        $loader = $this->loaderReturning(ContentDataLoaderResult::uncacheable(new StubStruct()));
        $cacheContext = new RenderingCacheContext();

        $this->resolveWith($loader, $this->elementWithRequirement('product'), $cacheContext);

        static::assertTrue($cacheContext->isDisabled());
    }

    #[TestDox('adds the cache tags of a cache aware result to the cache context')]
    public function testCacheAwareResultContributesItsTags(): void
    {
        $loader = $this->loaderReturning(
            ContentDataLoaderResult::cached(new StubStruct(), 'product-1', 'product-listing')
        );
        $cacheContext = new RenderingCacheContext();

        $this->resolveWith($loader, $this->elementWithRequirement('product'), $cacheContext);

        static::assertSame(['product-1', 'product-listing'], $cacheContext->getTags());
    }

    /**
     * The tag assertion is what makes this test able to fail. `isDisabled()` alone is already true after the
     * first requirement, so it holds whether the loop went on to the second requirement or stopped there.
     * `RenderingCacheContext::addTags()` carries no disabled guard, so a tag arriving from the second result
     * is proof that requirement was processed — the disable is sticky across it rather than ending the run.
     */
    #[TestDox('keeps the cache context disabled while still processing a later cache aware requirement')]
    public function testDisableIsNotReversedByALaterCacheAwareResult(): void
    {
        $loader = $this->loaderReturning(
            ContentDataLoaderResult::uncacheable(new StubStruct()),
            ContentDataLoaderResult::cached(new StubStruct(), 'category-1')
        );
        $cacheContext = new RenderingCacheContext();

        $this->resolveWith($loader, $this->elementWithTwoRequirements(), $cacheContext);

        static::assertTrue($cacheContext->isDisabled());
        static::assertSame(['category-1'], $cacheContext->getTags());
    }

    #[TestDox('returns an empty map for an element with no data requirements without asking for a loader')]
    public function testElementWithoutRequirementsNeverReachesTheProvider(): void
    {
        $provider = $this->createMock(DataLoaderProvider::class);
        $provider->expects($this->never())->method('get');
        $resolver = new ElementDataResolver($provider, new LoaderInputResolver(), $this->identityFactory());

        $resolved = $resolver->resolve(
            StoredElementBuilder::create('Sw:Text', 'element-1')->withProperty('headline', 'Hello')->build(),
            static::createStub(SalesChannelContext::class),
            new Request(),
            new RenderingCacheContext()
        );

        static::assertSame([], $resolved);
    }

    private function elementWithRequirement(string $key, string $activeId = 'the-active-id'): StoredElement
    {
        return StoredElementBuilder::create('Sw:ProductBox', 'element-1')
            ->withProperty('activeId', $activeId)
            ->withDataRequirement($key, 'entity', $this->config())
            ->build();
    }

    private function elementWithTwoRequirements(): StoredElement
    {
        return StoredElementBuilder::create('Sw:ProductBox', 'element-1')
            ->withProperty('activeId', 'the-active-id')
            ->withDataRequirement('product', 'entity', $this->config())
            ->withDataRequirement('category', 'entity', $this->config())
            ->build();
    }

    private function config(): TestNavigationShapedLoaderConfig
    {
        return new TestNavigationShapedLoaderConfig(entity: 'product', activeProperty: 'activeId');
    }

    /**
     * The identity factory is real rather than stubbed: it is what turns a resolved requirement into the
     * dedup key the response index reads, and a stub would let a wrong key through unnoticed.
     */
    private function identityFactory(): LoaderValueIdentityFactory
    {
        return new LoaderValueIdentityFactory(
            new DataLoaderConfigSerializerProvider(new ServiceLocator([
                'entity' => static fn (): TestNavigationShapedLoaderConfigSerializer => new TestNavigationShapedLoaderConfigSerializer(),
            ])),
            new ConfigCanonicalizer(),
            new ValueFingerprinter(),
        );
    }

    /**
     * @param AbstractContentDataLoader<Struct>&Stub $loader
     *
     * @return array<string, ResolvedLoaderValue>
     */
    private function resolveWith(
        AbstractContentDataLoader&Stub $loader,
        StoredElement $stored,
        ?RenderingCacheContext $cacheContext = null,
    ): array {
        $provider = static::createStub(DataLoaderProvider::class);
        $provider->method('get')->willReturn($loader);

        $resolver = new ElementDataResolver($provider, new LoaderInputResolver(), $this->identityFactory());

        return $resolver->resolve(
            $stored,
            static::createStub(SalesChannelContext::class),
            new Request(),
            $cacheContext ?? new RenderingCacheContext()
        );
    }

    /**
     * @return AbstractContentDataLoader<Struct>&Stub
     */
    private function loaderReturning(ContentDataLoaderResult ...$results): AbstractContentDataLoader&Stub
    {
        $loader = $this->loader();
        $loader->method('load')->willReturnOnConsecutiveCalls(...array_values($results));

        return $loader;
    }

    /**
     * @return AbstractContentDataLoader<Struct>&Stub
     */
    private function loader(): AbstractContentDataLoader&Stub
    {
        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('configSpecification')->willReturn(new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('activeProperty', ConfigKeyKind::PropertyReference, 'string', required: false),
        ]));

        return $loader;
    }
}
