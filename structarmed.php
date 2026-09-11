<?php

declare(strict_types=1);

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\Preset;
use Boundwize\StructArmed\Preset\Presets\Psr4Preset;
use Shopware\Administration\Administration;
use Shopware\Administration\Notification\NotificationCollection as AdminNotificationCollection;
use Shopware\Administration\Notification\NotificationEntity as AdminNotificationEntity;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Internal\InternalClassRule;
use Shopware\Core\Framework\Notification\NotificationCollection;
use Shopware\Core\Framework\Notification\NotificationEntity;
use Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacade;
use Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacadeHookFactory;
use Shopware\Core\Framework\Store\Helper\PermissionCategorization;
use Shopware\Core\Framework\Store\Services\StoreAppLifecycleService;
use Shopware\Core\System\Snippet\SnippetFileHandler;
use Shopware\Storefront\Controller\ScriptController;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Shopware\Storefront\Storefront;
use Shopware\Storefront\Theme\Aggregate\ThemeMediaDefinition;
use Shopware\Storefront\Theme\Aggregate\ThemeSalesChannelDefinition;
use Shopware\Storefront\Theme\Aggregate\ThemeTranslationDefinition;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeDefinition;

return Architecture::define()
    ->skip([
        Psr4Preset::CLASSES_MUST_MATCH_COMPOSER => [
            __DIR__ . '/src/Core/Framework/Adapter/Doctrine/Patch/AbstractAsset.php',
            'tests/**/data',
            'tests/unit',
            'tests/devops/Core/Migration',
            'tests/integration',
        ],
    ])
    ->withPresets(Preset::PSR4())
    ->layerPattern('Core', '#^Shopware\\\\Core\\\\#', '#(?:\\\\Exception\\\\|Exception$)#')
    ->layer('Administration', 'src/Administration/')
    ->layer('Storefront', 'src/Storefront/')
    ->layer('Elasticsearch', 'src/Elasticsearch/')
    ->ruleset([
        'Core' => [],
        'Administration' => ['Core'],
        'Storefront' => ['Core'],
        'Elasticsearch' => ['Core'],
    ])

    // Class names are used only to locate optional bundles' snippet directories.
    ->skipClassViolation(SnippetFileHandler::class, [Administration::class, Storefront::class])
    // The development rule compares a reflected parent class name.
    ->skipClassViolation(InternalClassRule::class, StorefrontController::class)
    // Theme definitions are referenced only by @see annotations for entity names.
    ->skipClassViolation(PermissionCategorization::class, [
        ThemeMediaDefinition::class,
        ThemeSalesChannelDefinition::class,
        ThemeTranslationDefinition::class,
        ThemeDefinition::class,
    ])
    // Optional theme repository; remove when shopware/shopware#12966 is resolved.
    ->skipClassViolation(StoreAppLifecycleService::class, ThemeCollection::class)
    // Remove with the v6.8.0 notification inheritance compatibility paths.
    ->skipClassViolation(NotificationCollection::class, [AdminNotificationCollection::class, AdminNotificationEntity::class])
    ->skipClassViolation(NotificationEntity::class, AdminNotificationEntity::class)
    // Remove with the v6.8.0 render() compatibility path.
    ->skipClassViolation(ScriptResponseFactoryFacade::class, ScriptController::class)
    ->skipClassViolation(ScriptResponseFactoryFacadeHookFactory::class, ScriptController::class)
    // Remove with the legacy cookie provider in the next major version.
    ->skipClassViolation(CookieProvider::class, CookieProviderInterface::class);
