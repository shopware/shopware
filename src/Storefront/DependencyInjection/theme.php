<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Plugin\BundleConfigStyleFileResolver;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Theme\AbstractThemePathBuilder;
use Shopware\Storefront\Theme\Aggregate\ThemeChildDefinition;
use Shopware\Storefront\Theme\Aggregate\ThemeMediaDefinition;
use Shopware\Storefront\Theme\Aggregate\ThemeSalesChannelDefinition;
use Shopware\Storefront\Theme\Aggregate\ThemeTranslationDefinition;
use Shopware\Storefront\Theme\BundleConfig\StorefrontBundleConfigStyleFileResolver;
use Shopware\Storefront\Theme\CachedResolvedConfigLoader;
use Shopware\Storefront\Theme\Command\ThemeChangeCommand;
use Shopware\Storefront\Theme\Command\ThemeCompileCommand;
use Shopware\Storefront\Theme\Command\ThemeCreateCommand;
use Shopware\Storefront\Theme\Command\ThemeDumpCommand;
use Shopware\Storefront\Theme\Command\ThemePrepareIconsCommand;
use Shopware\Storefront\Theme\Command\ThemeRefreshCommand;
use Shopware\Storefront\Theme\ConfigLoader\AbstractAvailableThemeProvider;
use Shopware\Storefront\Theme\ConfigLoader\AbstractConfigLoader;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseAvailableThemeProvider;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseConfigLoader;
use Shopware\Storefront\Theme\ConfigLoader\StaticFileAvailableThemeProvider;
use Shopware\Storefront\Theme\ConfigLoader\StaticFileConfigDumper;
use Shopware\Storefront\Theme\ConfigLoader\StaticFileConfigLoader;
use Shopware\Storefront\Theme\Controller\ThemeController;
use Shopware\Storefront\Theme\DataAbstractionLayer\ThemeExceptionHandler;
use Shopware\Storefront\Theme\DataAbstractionLayer\ThemeIndexer;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopware\Storefront\Theme\Extension\LanguageExtension;
use Shopware\Storefront\Theme\Extension\MediaExtension;
use Shopware\Storefront\Theme\Extension\SalesChannelExtension;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\Message\CompileThemeFailedSubscriber;
use Shopware\Storefront\Theme\Message\CompileThemeHandler;
use Shopware\Storefront\Theme\Message\DeleteThemeFilesHandler;
use Shopware\Storefront\Theme\ResolvedConfigLoader;
use Shopware\Storefront\Theme\ScheduledTask\DeleteThemeFilesTask;
use Shopware\Storefront\Theme\ScheduledTask\DeleteThemeFilesTaskHandler;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Shopware\Storefront\Theme\SeedingThemePathBuilder;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\Subscriber\FirstRunWizardSubscriber;
use Shopware\Storefront\Theme\Subscriber\PluginLifecycleSubscriber;
use Shopware\Storefront\Theme\Subscriber\ThemeCompilerEnrichScssVarSubscriber;
use Shopware\Storefront\Theme\Subscriber\ThemeSnippetsSubscriber;
use Shopware\Storefront\Theme\Subscriber\UnusedMediaSubscriber;
use Shopware\Storefront\Theme\Subscriber\UpdateSubscriber;
use Shopware\Storefront\Theme\ThemeAppLifecycleHandler;
use Shopware\Storefront\Theme\ThemeAssetPackage;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeConfigCacheInvalidator;
use Shopware\Storefront\Theme\ThemeDefinition;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeMergedConfigBuilder;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
use Shopware\Storefront\Theme\ThemeRuntimeConfigStorage;
use Shopware\Storefront\Theme\ThemeScripts;
use Shopware\Storefront\Theme\ThemeService;
use Shopware\Storefront\Theme\Twig\ThemeInheritanceBuilder;
use Shopware\Storefront\Theme\Twig\ThemeInheritanceBuilderInterface;
use Shopware\Storefront\Theme\Twig\ThemeNamespaceHierarchyBuilder;
use Shopware\Storefront\Theme\UnusedThemeDirectoryDeleter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Theme
    $services->set(StorefrontPluginConfigurationFactory::class)
        ->args([
            service(KernelPluginLoader::class),
            service(SourceResolver::class),
            service(Filesystem::class),
        ]);

    $services->set(StorefrontPluginRegistry::class)
        ->public()
        ->args([
            service('kernel'),
            service(StorefrontPluginConfigurationFactory::class),
            service(ActiveAppsLoader::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(BundleConfigStyleFileResolver::class, StorefrontBundleConfigStyleFileResolver::class)
        ->args([
            service(StorefrontPluginRegistry::class),
        ]);

    $services->set(ScssPhpCompiler::class);

    $services->set(ThemeCompiler::class)
        ->args([
            service('shopware.filesystem.theme'),
            service('shopware.filesystem.temp'),
            service('shopware.filesystem.asset'),
            service(CopyBatchInputFactory::class),
            service(ThemeFileResolver::class),
            param('kernel.debug'),
            service(EventDispatcherInterface::class),
            service(ThemeFilesystemResolver::class),
            tagged_iterator('shopware.asset'),
            service(CacheInvalidator::class),
            service(LoggerInterface::class),
            service(AbstractThemePathBuilder::class),
            service(ScssPhpCompiler::class),
            param('storefront.theme.allowed_scss_values'),
            param('storefront.theme.validate_on_compile'),
            param('shopware.filesystem.theme.visibility'),
        ]);

    $services->set(ThemeLifecycleService::class)
        ->args([
            service(StorefrontPluginRegistry::class),
            service('theme.repository'),
            service('media.repository'),
            service('media_folder.repository'),
            service('theme_media.repository'),
            service(FileSaver::class),
            service(FileNameProvider::class),
            service(ThemeFilesystemResolver::class),
            service('language.repository'),
            service('theme_child.repository'),
            service(Connection::class),
            service(StorefrontPluginConfigurationFactory::class)->nullOnInvalid(),
            service(ThemeRuntimeConfigService::class),
        ]);

    $services->set(ThemeFileResolver::class)
        ->args([
            service(ThemeFilesystemResolver::class),
        ]);

    $services->set(ThemeScripts::class)
        ->args([
            service('request_stack'),
            service(ThemeRuntimeConfigService::class),
            service('shopware.filesystem.temp'),
            service(LoggerInterface::class),
        ]);

    $services->set(ThemeMergedConfigBuilder::class)
        ->args([
            service(StorefrontPluginRegistry::class),
            service('theme.repository'),
        ]);

    $services->set(ThemeService::class)
        ->args([
            service(StorefrontPluginRegistry::class),
            service('theme.repository'),
            service('theme_sales_channel.repository'),
            service(ThemeCompiler::class),
            service(ScssPhpCompiler::class),
            service('event_dispatcher'),
            service(AbstractConfigLoader::class),
            service(Connection::class),
            service(SystemConfigService::class),
            service('messenger.default_bus'),
            service(NotificationService::class),
            service(ThemeMergedConfigBuilder::class),
            service(ThemeRuntimeConfigService::class),
        ]);

    $services->set(ResolvedConfigLoader::class)
        ->lazy()
        ->args([
            service('media.repository'),
            service(ThemeRuntimeConfigService::class),
        ]);

    $services->set(CachedResolvedConfigLoader::class)
        ->args([
            service(ResolvedConfigLoader::class),
        ])
        ->deprecate('shopware/core', '6.8.0', 'tag:v6.8.0 - The %service_id% service will be removed in v6.8.0.0 without replacement');

    $services->set(ThemeConfigCacheInvalidator::class)
        ->args([
            service(CacheInvalidator::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ThemeLifecycleHandler::class)
        ->args([
            service(ThemeLifecycleService::class),
            service(ThemeService::class),
            service('theme.repository'),
            service(StorefrontPluginRegistry::class),
            service(Connection::class),
        ]);

    $services->set(ThemeAppLifecycleHandler::class)
        ->args([
            service(StorefrontPluginRegistry::class),
            service(StorefrontPluginConfigurationFactory::class),
            service(ThemeLifecycleHandler::class),
            service(ThemeLifecycleService::class),
        ])
        ->tag('shopware.app_lifecycle.handler');

    $services->set(DatabaseAvailableThemeProvider::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(DatabaseConfigLoader::class)
        ->args([
            service('theme.repository'),
            service(StorefrontPluginRegistry::class),
            service('media.repository'),
        ]);

    $services->set(ThemeRuntimeConfigStorage::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(ThemeRuntimeConfigService::class)
        ->args([
            service(ThemeFileResolver::class),
            service(StorefrontPluginRegistry::class),
            service(ThemeMergedConfigBuilder::class),
            service(ThemeRuntimeConfigStorage::class),
            service(ClockInterface::class),
        ]);

    $services->set(SeedingThemePathBuilder::class)
        ->lazy()
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(MD5ThemePathBuilder::class);

    $services->set(DeleteThemeFilesHandler::class)
        ->args([
            service('shopware.filesystem.theme'),
            service(AbstractThemePathBuilder::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(CompileThemeHandler::class)
        ->args([
            service(ThemeCompiler::class),
            service(AbstractConfigLoader::class),
            service(StorefrontPluginRegistry::class),
            service(NotificationService::class),
            service('sales_channel.repository'),
            service(ThemeRuntimeConfigService::class),
            service('theme_sales_channel.repository'),
            service('event_dispatcher'),
            service(SystemConfigService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(CompileThemeFailedSubscriber::class)
        ->args([
            service(NotificationService::class),
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UnusedThemeDirectoryDeleter::class)
        ->args([
            service(Connection::class),
            service('shopware.filesystem.theme'),
            service(AbstractThemePathBuilder::class),
            service(ClockInterface::class),
        ]);

    $services->set(DeleteThemeFilesTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(DeleteThemeFilesTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(UnusedThemeDirectoryDeleter::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(StaticFileConfigLoader::class)
        ->args([
            service('shopware.filesystem.private'),
        ]);

    $services->set(StaticFileAvailableThemeProvider::class)
        ->args([
            service('shopware.filesystem.private'),
        ]);

    $services->set(StaticFileConfigDumper::class)
        ->args([
            service(DatabaseConfigLoader::class),
            service(DatabaseAvailableThemeProvider::class),
            service('shopware.filesystem.private'),
            service('shopware.filesystem.temp'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set('shopware.asset.theme', ThemeAssetPackage::class)
        ->lazy()
        ->args([
            [
                param('shopware.filesystem.theme.url'),
            ],
            service('shopware.asset.theme.version_strategy'),
            service('request_stack'),
            service(AbstractThemePathBuilder::class),
        ])
        ->tag('shopware.asset', ['asset' => 'theme']);

    // Entity definitions
    $services->set(ThemeDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ThemeTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ThemeSalesChannelDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ThemeMediaDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ThemeChildDefinition::class)
        ->tag('shopware.entity.definition');

    // Entity extensions
    $services->set(SalesChannelExtension::class)
        ->tag('shopware.entity.extension');

    $services->set(LanguageExtension::class)
        ->tag('shopware.entity.extension');

    $services->set(MediaExtension::class)
        ->tag('shopware.entity.extension');

    // Controller
    $services->set(ThemeController::class)
        ->public()
        ->args([
            service(ThemeService::class),
            service(ScssPhpCompiler::class),
            param('storefront.theme.allowed_scss_values'),
        ])
        ->call('setContainer', [service('service_container')]);

    // Commands
    $services->set(ThemeCreateCommand::class)
        ->args([
            param('kernel.project_dir'),
            service(Filesystem::class),
        ])
        ->tag('console.command');

    $services->set(ThemeChangeCommand::class)
        ->args([
            service(ThemeService::class),
            service(StorefrontPluginRegistry::class),
            service('sales_channel.repository'),
            service('theme.repository'),
            service(UnusedThemeDirectoryDeleter::class),
        ])
        ->tag('console.command');

    $services->set(ThemeCompileCommand::class)
        ->args([
            service(ThemeService::class),
            service(AbstractAvailableThemeProvider::class),
            service(ClockInterface::class),
            service(UnusedThemeDirectoryDeleter::class),
        ])
        ->tag('console.command');

    $services->set(ThemeDumpCommand::class)
        ->args([
            service(StorefrontPluginRegistry::class),
            service(ThemeFileResolver::class),
            service('theme.repository'),
            service(StaticFileConfigDumper::class),
            service(ThemeFilesystemResolver::class),
        ])
        ->tag('console.command');

    $services->set(ThemeRefreshCommand::class)
        ->args([
            service(ThemeLifecycleService::class),
        ])
        ->tag('console.command');

    $services->set(ThemePrepareIconsCommand::class)
        ->tag('console.command');

    // Subscriber
    $services->set(PluginLifecycleSubscriber::class)
        ->args([
            service(StorefrontPluginRegistry::class),
            param('kernel.project_dir'),
            service(StorefrontPluginConfigurationFactory::class),
            service(ThemeLifecycleHandler::class),
            service(ThemeLifecycleService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ThemeInheritanceBuilderInterface::class, ThemeInheritanceBuilder::class)
        ->args([
            service(ThemeRuntimeConfigService::class),
        ]);

    $services->set(ThemeCompilerEnrichScssVarSubscriber::class)
        ->args([
            service(ConfigurationService::class),
            service(StorefrontPluginRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    // Twig
    $services->set(ThemeNamespaceHierarchyBuilder::class)
        ->args([
            service(ThemeInheritanceBuilderInterface::class),
            service(DatabaseSalesChannelThemeLoader::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset'])
        // Make sure it runs after default `BundleHierarchyBuilder`
        ->tag('shopware.twig.hierarchy_builder', ['priority' => 500]);

    $services->set(FirstRunWizardSubscriber::class)
        ->args([
            service(ThemeService::class),
            service(ThemeLifecycleService::class),
            service('theme.repository'),
            service('theme_sales_channel.repository'),
            service('sales_channel.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UpdateSubscriber::class)
        ->args([
            service(ThemeService::class),
            service(ThemeLifecycleService::class),
            service('sales_channel.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UnusedMediaSubscriber::class)
        ->args([
            service('theme.repository'),
            service(ThemeService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ThemeIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('theme.repository'),
            service(Connection::class),
            service('event_dispatcher'),
        ])
        ->tag('shopware.entity_indexer');

    $services->set(DatabaseSalesChannelThemeLoader::class)
        ->public()
        ->args([
            service(Connection::class),
        ]);

    $services->set(ThemeExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(ThemeFilesystemResolver::class)
        ->public()
        ->args([
            service(SourceResolver::class),
            service('kernel'),
        ]);

    $services->set(ThemeSnippetsSubscriber::class)
        ->args([
            service(ThemeRuntimeConfigService::class),
            service(DatabaseSalesChannelThemeLoader::class),
        ])
        ->tag('kernel.event_subscriber');
};
