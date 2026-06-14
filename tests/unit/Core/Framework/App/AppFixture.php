<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
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

    public static function createAppEntity(string $name = 'testApp', ?string $id = null, bool $active = true, bool $allowDisable = true): AppEntity
    {
        $app = new AppEntity();
        $app->setId($id ?? Uuid::randomHex());
        $app->setName($name);
        $app->setPath($name);
        $app->setActive($active);
        $app->setAllowDisable($allowDisable);
        $app->setVersion('1.0.0');
        $app->setIntegrationId('integration-id');
        $app->setAclRoleId('acl-role-id');
        $app->setSourceType('static');

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

    /**
     * @return StaticEntityRepository<LanguageCollection>
     */
    public static function createLanguageRepository(string $locale = 'en-GB'): StaticEntityRepository
    {
        $localeEntity = new LocaleEntity();
        $localeEntity->assign(['code' => $locale]);

        $languageEntity = new LanguageEntity();
        $languageEntity->assign([
            'id' => 'language-id',
            'translationCode' => $localeEntity,
        ]);

        /** @var StaticEntityRepository<LanguageCollection> $repository */
        $repository = new StaticEntityRepository([new LanguageCollection([$languageEntity])]);

        return $repository;
    }

    public static function createInstallContext(
        AppEntity $app,
        Manifest $manifest,
        ?Filesystem $appFilesystem = null,
        string $defaultLocale = 'en-GB'
    ): AppPersistContext {
        return self::createPersistContext($app, $manifest, $appFilesystem ?? new StaticFilesystem(), $defaultLocale);
    }

    public static function createUpdateContext(
        AppEntity $app,
        Manifest $manifest,
        ?Filesystem $appFilesystem = null,
        string $defaultLocale = 'en-GB'
    ): AppPersistContext {
        return self::createPersistContext($app, $manifest, $appFilesystem ?? new StaticFilesystem(), $defaultLocale);
    }

    private static function createPersistContext(
        AppEntity $app,
        Manifest $manifest,
        Filesystem $fs,
        string $defaultLocale
    ): AppPersistContext {
        return new AppPersistContext(
            manifest: $manifest,
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: $fs,
            defaultLocale: $defaultLocale,
        );
    }
}
