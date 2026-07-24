<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Module;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppSecretResolver;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\Module\ModuleConfig;
use Shopware\Core\Framework\App\Module\ModuleLoader;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ModuleLoader::class)]
class ModuleLoaderTest extends TestCase
{
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
}
