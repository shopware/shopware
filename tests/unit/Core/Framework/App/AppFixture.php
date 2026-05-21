<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * Helpers for testing app lifecycle components in unit tests
 *
 * @internal
 */
final class AppFixture
{
    private function __construct()
    {
    }

    public static function createAppEntity(string $name = 'testApp', ?string $id = null): AppEntity
    {
        $app = new AppEntity();
        $app->setId($id ?? Uuid::randomHex());
        $app->setName($name);
        $app->setPath($name);
        $app->setActive(true);

        return $app;
    }

    /**
     * @return StaticEntityRepository<AppCollection>
     */
    public static function createAppRepository(AppEntity ...$apps): StaticEntityRepository
    {
        /** @var StaticEntityRepository<AppCollection> $repository */
        $repository = new StaticEntityRepository([new AppCollection($apps)]);

        return $repository;
    }

    public static function createInstallContext(
        AppEntity $app,
        Manifest $manifest,
        ?Filesystem $appFilesystem = null,
        string $defaultLocale = 'en-GB'
    ): AppLifecycleContext {
        return self::createContext($app, $manifest, $appFilesystem ?? new StaticFilesystem(), $defaultLocale, true);
    }

    public static function createUpdateContext(
        AppEntity $app,
        Manifest $manifest,
        ?Filesystem $appFilesystem = null,
        string $defaultLocale = 'en-GB'
    ): AppLifecycleContext {
        return self::createContext($app, $manifest, $appFilesystem ?? new StaticFilesystem(), $defaultLocale, false);
    }

    private static function createContext(
        AppEntity $app,
        Manifest $manifest,
        Filesystem $fs,
        string $defaultLocale,
        bool $isInstall
    ): AppLifecycleContext {
        return new AppLifecycleContext(
            manifest: $manifest,
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: $fs,
            defaultLocale: $defaultLocale,
            isInstall: $isInstall,
        );
    }
}
