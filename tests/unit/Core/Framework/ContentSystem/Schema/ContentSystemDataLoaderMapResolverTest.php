<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\ContentSystem\Fixture\LoaderConfigSpecificationFixture;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemDataLoaderMapResolver::class)]
class ContentSystemDataLoaderMapResolverTest extends TestCase
{
    #[TestDox('assembles the map from each registered loader producibleTypes() keyed by source')]
    public function testAssemblesMapFromProducibleTypes(): void
    {
        $navigation = static::createStub(AbstractContentDataLoader::class);
        $navigation->method('producibleTypes')->willReturn([new LoaderTypeCapability(Tree::class)]);
        $navigation->method('configSpecification')->willReturn(new LoaderConfigSpecification([]));

        $entity = static::createStub(AbstractContentDataLoader::class);
        $entity->method('producibleTypes')->willReturn([
            new LoaderTypeCapability(SalesChannelProductEntity::class, ['entity' => 'product']),
        ]);
        $entity->method('configSpecification')->willReturn(new LoaderConfigSpecification([]));

        $resolver = new ContentSystemDataLoaderMapResolver(new DataLoaderProvider(new ServiceLocator([
            'navigation' => static fn (): AbstractContentDataLoader => $navigation,
            'entity' => static fn (): AbstractContentDataLoader => $entity,
        ])));

        $map = $resolver->resolve();

        static::assertSame([Tree::class], array_map(
            static fn (LoaderTypeCapability $capability): string => $capability->producedType,
            $map->sourceToCapabilities['navigation'],
        ));
        static::assertSame(['entity' => 'product'], $map->sourceToCapabilities['entity'][0]->configTemplate);
    }

    #[TestDox('assembles each registered loader configSpecification() into the map keyed by source')]
    public function testAssemblesConfigSpecificationsFromLoaders(): void
    {
        $navigationSpecification = new LoaderConfigSpecification([]);
        $navigation = static::createStub(AbstractContentDataLoader::class);
        $navigation->method('producibleTypes')->willReturn([new LoaderTypeCapability(Tree::class)]);
        $navigation->method('configSpecification')->willReturn($navigationSpecification);

        $entitySpecification = LoaderConfigSpecificationFixture::entityProperty();
        $entity = static::createStub(AbstractContentDataLoader::class);
        $entity->method('producibleTypes')->willReturn([
            new LoaderTypeCapability(SalesChannelProductEntity::class, ['entity' => 'product']),
        ]);
        $entity->method('configSpecification')->willReturn($entitySpecification);

        $resolver = new ContentSystemDataLoaderMapResolver(new DataLoaderProvider(new ServiceLocator([
            'navigation' => static fn (): AbstractContentDataLoader => $navigation,
            'entity' => static fn (): AbstractContentDataLoader => $entity,
        ])));

        $map = $resolver->resolve();

        static::assertSame($navigationSpecification, $map->configSpecificationFor('navigation'));
        static::assertSame($entitySpecification, $map->configSpecificationFor('entity'));
    }

    #[TestDox('memoizes the assembled map and reads each loader only once per runtime')]
    public function testMemoizesAssembledMap(): void
    {
        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('producibleTypes')->willReturn([new LoaderTypeCapability(Tree::class)]);
        $loader->method('configSpecification')->willReturn(new LoaderConfigSpecification([]));

        $resolver = new ContentSystemDataLoaderMapResolver(new DataLoaderProvider(new ServiceLocator([
            'navigation' => static fn (): AbstractContentDataLoader => $loader,
        ])));

        static::assertSame($resolver->resolve(), $resolver->resolve());
    }

    #[TestDox('re-reads producibleTypes() on each fresh resolver instance so late-registered types appear without a container rebuild')]
    public function testFreshResolverReflectsLateRegisteredTypes(): void
    {
        $capabilities = [new LoaderTypeCapability(Tree::class)];

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('producibleTypes')->willReturnCallback(static function () use (&$capabilities): array {
            return $capabilities;
        });
        $loader->method('configSpecification')->willReturn(new LoaderConfigSpecification([]));

        $provider = new DataLoaderProvider(new ServiceLocator([
            'navigation' => static fn (): AbstractContentDataLoader => $loader,
        ]));

        $first = (new ContentSystemDataLoaderMapResolver($provider))->resolve();
        static::assertCount(1, $first->sourceToCapabilities['navigation']);

        // Simulate an entity registered into the live registry between kernel runtimes.
        $capabilities[] = new LoaderTypeCapability(SalesChannelProductEntity::class);

        $second = (new ContentSystemDataLoaderMapResolver($provider))->resolve();
        static::assertCount(2, $second->sourceToCapabilities['navigation']);
    }

    #[TestDox('resolves an empty map when no loader is registered')]
    public function testResolveWithoutRegisteredLoadersYieldsEmptyMap(): void
    {
        $resolver = new ContentSystemDataLoaderMapResolver(new DataLoaderProvider(new ServiceLocator([])));

        static::assertSame([], $resolver->resolve()->sourceToCapabilities);
    }
}
