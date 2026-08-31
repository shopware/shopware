<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\Manifest\ModuleLoader;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ModuleLoader::class)]
class ModuleLoaderTest extends TestCase
{
    public function testLoadModulesReturnsNothingWhenShopIdChangeWasSuggested(): void
    {
        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('getShopId')->willThrowException(
            new ShopIdChangeSuggestedException(ShopId::v2('shop-id'), new FingerprintComparisonResult([], [], 75))
        );

        $moduleLoader = new ModuleLoader(
            new StaticEntityRepository([new AppCollection()]),
            $shopIdProvider,
            static::createStub(QuerySigner::class),
        );

        static::assertSame([], $moduleLoader->loadModules(Context::createDefaultContext()));
    }

    public function testLoadModulesFormatsAuthorizedModulesAndMainModule(): void
    {
        $app = AppFixture::createAppEntity('AllowedApp');
        $app->setModules([
            [
                'label' => ['en-GB' => 'module without source'],
                'source' => null,
                'name' => 'module-without-source',
                'parent' => 'sw-catalogue',
                'position' => 10,
            ],
            [
                'label' => ['en-GB' => 'module with source'],
                'source' => 'https://module.app.com',
                'name' => 'module-with-source',
                'parent' => 'sw-catalogue',
                'position' => 20,
            ],
        ]);
        $app->setMainModule([
            'source' => 'https://main.app.com',
            'name' => 'main-module',
            'label' => ['en-GB' => 'main module'],
            'parent' => 'sw-catalogue',
            'position' => 0,
        ]);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('getShopId')->willReturn(ShopId::v2('shop-id'));

        $querySigner = $this->createMock(QuerySigner::class);
        $querySigner->expects($this->exactly(2))->method('signUri')->willReturnCallback(
            static fn (string $source): Uri => new Uri($source . '?signed=true')
        );

        $source = new AdminApiSource(null);
        $source->setPermissions(['app.AllowedApp']);

        $moduleLoader = new ModuleLoader(
            new StaticEntityRepository([new AppCollection([$app])]),
            $shopIdProvider,
            $querySigner,
        );

        static::assertSame([
            [
                'name' => 'AllowedApp',
                'label' => [],
                'modules' => [
                    [
                        'label' => ['en-GB' => 'module without source'],
                        'source' => null,
                        'name' => 'module-without-source',
                        'parent' => 'sw-catalogue',
                        'position' => 10,
                    ],
                    [
                        'label' => ['en-GB' => 'module with source'],
                        'source' => 'https://module.app.com?signed=true',
                        'name' => 'module-with-source',
                        'parent' => 'sw-catalogue',
                        'position' => 20,
                    ],
                ],
                'mainModule' => ['source' => 'https://main.app.com?signed=true'],
            ],
        ], $moduleLoader->loadModules(Context::createDefaultContext($source)));
    }

    public function testLoadModulesSkipsAppsWithoutModulesOrMainModule(): void
    {
        $app = AppFixture::createAppEntity('EmptyApp');
        $app->setModules([]);
        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('getShopId')->willReturn(ShopId::v2('shop-id'));

        $moduleLoader = new ModuleLoader(
            new StaticEntityRepository([new AppCollection([$app])]),
            $shopIdProvider,
            static::createStub(QuerySigner::class),
        );

        static::assertSame([], $moduleLoader->loadModules(Context::createDefaultContext()));
    }

    public function testLoadModulesSkipsAppsWithoutPermissionBeforeSigningModules(): void
    {
        $app = AppFixture::createAppEntity('ForbiddenApp');
        $app->setModules([
            [
                'label' => ['en-GB' => 'forbidden module'],
                'source' => 'https://forbidden.app.com',
                'name' => 'forbidden-module',
                'parent' => 'sw-catalogue',
                'position' => 50,
            ],
        ]);

        $querySigner = $this->createMock(QuerySigner::class);
        $querySigner->expects($this->never())->method('signUri');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('getShopId')->willReturn(ShopId::v2('shop-id'));

        $source = new AdminApiSource(null);
        $source->setPermissions(['app.AllowedApp']);

        $moduleLoader = new ModuleLoader(
            new StaticEntityRepository([new AppCollection([$app])]),
            $shopIdProvider,
            $querySigner,
        );

        static::assertSame([], $moduleLoader->loadModules(Context::createDefaultContext($source)));
    }
}
