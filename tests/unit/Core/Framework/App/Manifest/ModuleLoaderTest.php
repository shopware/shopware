<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\Manifest\ModuleLoader;
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
