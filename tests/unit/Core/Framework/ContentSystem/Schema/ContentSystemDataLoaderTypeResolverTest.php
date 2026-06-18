<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeResolver;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[CoversClass(ContentSystemDataLoaderTypeResolver::class)]
class ContentSystemDataLoaderTypeResolverTest extends TestCase
{
    #[TestDox('assembles the map from each registered loader producibleTypes() keyed by source')]
    public function testAssemblesMapFromProducibleTypes(): void
    {
        $navigation = static::createStub(AbstractContentDataLoader::class);
        $navigation->method('producibleTypes')->willReturn([new LoaderTypeCapability(Tree::class)]);

        $entity = static::createStub(AbstractContentDataLoader::class);
        $entity->method('producibleTypes')->willReturn([
            new LoaderTypeCapability(SalesChannelProductEntity::class, ['entity' => 'product'], ['property']),
        ]);

        $resolver = new ContentSystemDataLoaderTypeResolver(new DataLoaderProvider(new ServiceLocator([
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

    #[TestDox('memoizes the assembled map so producibleTypes() is read only once per runtime')]
    public function testMemoizesAssembledMap(): void
    {
        $loader = $this->createMock(AbstractContentDataLoader::class);
        $loader->expects($this->once())->method('producibleTypes')->willReturn([new LoaderTypeCapability(Tree::class)]);

        $resolver = new ContentSystemDataLoaderTypeResolver(new DataLoaderProvider(new ServiceLocator([
            'navigation' => static fn (): AbstractContentDataLoader => $loader,
        ])));

        static::assertSame($resolver->resolve(), $resolver->resolve());
    }

    #[TestDox('a fresh resolver re-reads producibleTypes() so late-registered types appear without a container rebuild')]
    public function testFreshResolverReflectsLateRegisteredTypes(): void
    {
        $capabilities = [new LoaderTypeCapability(Tree::class)];

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('producibleTypes')->willReturnCallback(static function () use (&$capabilities): array {
            return $capabilities;
        });

        $provider = new DataLoaderProvider(new ServiceLocator([
            'navigation' => static fn (): AbstractContentDataLoader => $loader,
        ]));

        $first = (new ContentSystemDataLoaderTypeResolver($provider))->resolve();
        static::assertCount(1, $first->sourceToCapabilities['navigation']);

        // Simulate an entity registered into the live registry between kernel runtimes.
        $capabilities[] = new LoaderTypeCapability(SalesChannelProductEntity::class);

        $second = (new ContentSystemDataLoaderTypeResolver($provider))->resolve();
        static::assertCount(2, $second->sourceToCapabilities['navigation']);
    }
}
