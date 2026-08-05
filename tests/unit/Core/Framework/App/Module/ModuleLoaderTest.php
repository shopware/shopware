<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Module;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppSecretResolver;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\Module\MainModule;
use Shopware\Core\Framework\App\Module\Module;
use Shopware\Core\Framework\App\Module\ModuleConfig;
use Shopware\Core\Framework\App\Module\ModuleLoader;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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
        $app = AppFixture::createAppEntity('AllowedApp');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('getShopId')->willThrowException(
            new ShopIdChangeSuggestedException(ShopId::v2('shop-id'), new FingerprintComparisonResult([], [], 75))
        );

        $moduleLoader = new ModuleLoader(
            new StaticEntityRepository([new AppCollection([$app])], new AppDefinition()),
            $shopIdProvider,
            static::createStub(QuerySigner::class),
            $this->storageWithFeature($this->feature($app, modules: [
                new Module('some-module', new TranslatedString(['en-GB' => 'some module']), 'sw-catalogue', 'https://module.app.com', 10),
            ])),
            $this->secretResolver(),
        );

        static::assertSame([], $moduleLoader->loadModules(Context::createDefaultContext()));
    }

    public function testLoadModulesFormatsAuthorizedModulesAndMainModule(): void
    {
        $app = AppFixture::createAppEntity('AllowedApp');
        $feature = $this->feature(
            $app,
            modules: [
                new Module('module-without-source', new TranslatedString(['en-GB' => 'module without source']), 'sw-catalogue', null, 10),
                new Module('module-with-source', new TranslatedString(['en-GB' => 'module with source']), 'sw-catalogue', 'https://module.app.com', 20),
            ],
            mainModule: new MainModule('https://main.app.com'),
        );

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('getShopId')->willReturn(ShopId::v2('shop-id'));

        $querySigner = $this->createMock(QuerySigner::class);
        $querySigner->expects($this->exactly(2))->method('signUriFor')->willReturnCallback(
            static fn (string $source): Uri => new Uri($source . '?signed=true')
        );

        $source = new AdminApiSource(null);
        $source->setPermissions(['app.AllowedApp']);

        $moduleLoader = new ModuleLoader(
            new StaticEntityRepository([new AppCollection([$app])], new AppDefinition()),
            $shopIdProvider,
            $querySigner,
            $this->storageWithFeature($feature),
            $this->secretResolver(),
        );

        static::assertSame([
            [
                'name' => 'AllowedApp',
                'label' => [],
                'modules' => [
                    [
                        'name' => 'module-without-source',
                        'label' => ['en-GB' => 'module without source'],
                        'parent' => 'sw-catalogue',
                        'source' => null,
                        'position' => 10,
                    ],
                    [
                        'name' => 'module-with-source',
                        'label' => ['en-GB' => 'module with source'],
                        'parent' => 'sw-catalogue',
                        'source' => 'https://module.app.com?signed=true',
                        'position' => 20,
                    ],
                ],
                'mainModule' => ['source' => 'https://main.app.com?signed=true'],
            ],
        ], $moduleLoader->loadModules(Context::createDefaultContext($source)));
    }

    public function testLoadModulesSkipsTheAppQueryWhenThereAreNoActiveModuleFeatures(): void
    {
        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository->expects($this->never())->method('search');

        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->once())
            ->method('forActiveApps')
            ->with(ModuleConfig::class)
            ->willReturn([]);

        /** @var EntityRepository<AppCollection>&MockObject $appRepository */
        $loader = new ModuleLoader(
            $appRepository,
            static::createStub(ShopIdProvider::class),
            static::createStub(QuerySigner::class),
            $storage,
            static::createStub(AppSecretResolver::class),
        );

        static::assertSame([], $loader->loadModules(Context::createDefaultContext()));
    }

    public function testLoadModulesSkipsAppsWithoutModulesOrMainModule(): void
    {
        $app = AppFixture::createAppEntity('EmptyApp');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('getShopId')->willReturn(ShopId::v2('shop-id'));

        $moduleLoader = new ModuleLoader(
            new StaticEntityRepository([new AppCollection([$app])], new AppDefinition()),
            $shopIdProvider,
            static::createStub(QuerySigner::class),
            $this->storageWithFeature($this->feature($app, modules: [])),
            $this->secretResolver(),
        );

        static::assertSame([], $moduleLoader->loadModules(Context::createDefaultContext()));
    }

    public function testLoadModulesSkipsAppsWithoutPermissionBeforeSigningModules(): void
    {
        $app = AppFixture::createAppEntity('ForbiddenApp');

        $querySigner = $this->createMock(QuerySigner::class);
        $querySigner->expects($this->never())->method('signUriFor');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('getShopId')->willReturn(ShopId::v2('shop-id'));

        $source = new AdminApiSource(null);
        $source->setPermissions(['app.AllowedApp']);

        $moduleLoader = new ModuleLoader(
            new StaticEntityRepository([new AppCollection([$app])], new AppDefinition()),
            $shopIdProvider,
            $querySigner,
            $this->storageWithFeature($this->feature($app, modules: [
                new Module('forbidden-module', new TranslatedString(['en-GB' => 'forbidden module']), 'sw-catalogue', 'https://forbidden.app.com', 50),
            ])),
            $this->secretResolver(),
        );

        static::assertSame([], $moduleLoader->loadModules(Context::createDefaultContext($source)));
    }

    /**
     * @param list<Module> $modules
     *
     * @return AppFeature<ModuleConfig>
     */
    private function feature(AppEntity $app, array $modules, ?MainModule $mainModule = null): AppFeature
    {
        return new AppFeature(
            $app->getId(),
            $app->getName(),
            true,
            $app->getVersion(),
            true,
            new \DateTimeImmutable(),
            new ModuleConfig($modules, $mainModule),
        );
    }

    /**
     * @param AppFeature<ModuleConfig> $feature
     */
    private function storageWithFeature(AppFeature $feature): AppFeatureStorage
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([$feature]);

        return $storage;
    }

    private function secretResolver(): AppSecretResolver
    {
        $secretResolver = static::createStub(AppSecretResolver::class);
        $secretResolver->method('resolve')->willReturn('s3cr3t');

        return $secretResolver;
    }
}
