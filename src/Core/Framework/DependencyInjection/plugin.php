<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Composer\Autoload\ClassLoader;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationDefinition;
use Shopware\Core\Framework\Plugin\BundleConfigGenerator;
use Shopware\Core\Framework\Plugin\BundleConfigStyleFileResolver;
use Shopware\Core\Framework\Plugin\Command\BundleDumpCommand;
use Shopware\Core\Framework\Plugin\Command\Lifecycle\PluginActivateCommand;
use Shopware\Core\Framework\Plugin\Command\Lifecycle\PluginDeactivateCommand;
use Shopware\Core\Framework\Plugin\Command\Lifecycle\PluginInstallCommand;
use Shopware\Core\Framework\Plugin\Command\Lifecycle\PluginUninstallCommand;
use Shopware\Core\Framework\Plugin\Command\Lifecycle\PluginUpdateAllCommand;
use Shopware\Core\Framework\Plugin\Command\Lifecycle\PluginUpdateCommand;
use Shopware\Core\Framework\Plugin\Command\MakerCommand;
use Shopware\Core\Framework\Plugin\Command\PluginCreateCommand;
use Shopware\Core\Framework\Plugin\Command\PluginListCommand;
use Shopware\Core\Framework\Plugin\Command\PluginRefreshCommand;
use Shopware\Core\Framework\Plugin\Command\PluginZipImportCommand;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\AdminModuleGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\CommandGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ComposerGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ConfigGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\CustomFieldsetGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\EntityGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\EventSubscriberGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\GitignoreGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\JavascriptPluginGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\PluginClassGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScheduledTaskGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\StoreApiRouteGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\StorefrontControllerGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\TestsGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingCollector;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingWriter;
use Shopware\Core\Framework\Plugin\Composer\CommandExecutor;
use Shopware\Core\Framework\Plugin\Composer\PackageProvider;
use Shopware\Core\Framework\Plugin\ExtensionExtractor;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\NullBundleConfigStyleFileResolver;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\PluginZipDetector;
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\Framework\Plugin\Subscriber\PluginAclPrivilegesSubscriber;
use Shopware\Core\Framework\Plugin\Subscriber\PluginLoadedSubscriber;
use Shopware\Core\Framework\Plugin\Telemetry\PluginTelemetrySubscriber;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\Plugin\Util\VersionSanitizer;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\CustomField\CustomFieldSetPersister;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set('maker.auto_command.abstract', MakerCommand::class)
        ->abstract()
        ->args([
            '', // maker
            service(ScaffoldingCollector::class),
            service(ScaffoldingWriter::class),
            service(PluginService::class),
        ]);

    $services->set(KernelPluginLoader::class)
        ->public()
        ->factory([service('kernel'), 'getPluginLoader']);

    $services->set(ClassLoader::class)
        ->factory([service(KernelPluginLoader::class), 'getClassLoader']);

    $services->set(KernelPluginCollection::class)
        ->public()
        ->factory([service(KernelPluginLoader::class), 'getPluginInstances']);

    $services->set(BundleDumpCommand::class)
        ->args([
            service(BundleConfigGenerator::class),
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set(BundleConfigStyleFileResolver::class, NullBundleConfigStyleFileResolver::class);

    $services->set(BundleConfigGenerator::class)
        ->args([
            service('kernel'),
            service(ActiveAppsLoader::class),
            service(BundleConfigStyleFileResolver::class),
        ]);

    $services->set(PluginDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PluginTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PluginService::class)
        ->args([
            param('kernel.plugin_dir'),
            param('kernel.project_dir'),
            service('plugin.repository'),
            service('language.repository'),
            service(PluginFinder::class),
            service(VersionSanitizer::class),
        ]);

    $services->set(PluginLifecycleService::class)
        ->args([
            service('plugin.repository'),
            service('event_dispatcher'),
            service(KernelPluginCollection::class),
            service('service_container'),
            service(MigrationCollectionLoader::class),
            service(AssetService::class),
            service(CommandExecutor::class),
            service(RequirementsValidator::class),
            service('cache.messenger.restart_workers_signal'),
            param('kernel.shopware_version'),
            service(SystemConfigService::class),
            service(CustomEntityPersister::class),
            service(CustomEntitySchemaUpdater::class),
            service(PluginService::class),
            service(VersionSanitizer::class),
            service(DefinitionInstanceRegistry::class),
            service(RequestStack::class),
            service(CustomFieldSetPersister::class),
            service(ClockInterface::class),
        ]);

    $services->set(PluginManagementService::class)
        ->args([
            param('kernel.project_dir'),
            service(PluginZipDetector::class),
            service(ExtensionExtractor::class),
            service(PluginService::class),
            service(Filesystem::class),
            service(CacheClearer::class),
            service('shopware.store_download_client'),
        ]);

    $services->set(ExtensionExtractor::class)
        ->args([
            [
                'plugin' => param('kernel.plugin_dir'),
                'app' => param('kernel.app_dir'),
            ],
            service(Filesystem::class),
        ]);

    $services->set(PluginZipDetector::class);

    $services->set(ComposerPluginLoader::class)
        ->args([
            service(ClassLoader::class),
        ]);

    // Commands
    $services->set(PluginRefreshCommand::class)
        ->args([
            service(PluginService::class),
        ])
        ->tag('console.command');

    $services->set(PluginListCommand::class)
        ->args([
            service('plugin.repository'),
            service(ComposerPluginLoader::class),
        ])
        ->tag('console.command');

    $services->set(PluginZipImportCommand::class)
        ->args([
            service(PluginManagementService::class),
            service(PluginService::class),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(PluginInstallCommand::class)
        ->args([
            service(PluginLifecycleService::class),
            service('plugin.repository'),
            service(CacheClearer::class),
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set(PluginActivateCommand::class)
        ->args([
            service(PluginLifecycleService::class),
            service('plugin.repository'),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(PluginUpdateCommand::class)
        ->args([
            service(PluginLifecycleService::class),
            service('plugin.repository'),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(PluginDeactivateCommand::class)
        ->args([
            service(PluginLifecycleService::class),
            service('plugin.repository'),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(PluginUninstallCommand::class)
        ->args([
            service(PluginLifecycleService::class),
            service('plugin.repository'),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(PluginUpdateAllCommand::class)
        ->args([
            service(PluginService::class),
            service('plugin.repository'),
            service(PluginLifecycleService::class),
        ])
        ->tag('console.command');

    $services->set(PluginLoadedSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(PluginAclPrivilegesSubscriber::class)
        ->args([
            service(KernelPluginCollection::class),
        ])
        ->tag('kernel.event_subscriber');

    // Composer
    $services->set(PackageProvider::class);

    $services->set(CommandExecutor::class)
        ->lazy()
        ->args([
            param('kernel.project_dir'),
        ]);

    // Helper
    $services->set(PluginIdProvider::class)
        ->public()
        ->args([
            service('plugin.repository'),
        ]);

    $services->set(AssetService::class)
        ->args([
            service('shopware.filesystem.asset'),
            service('shopware.filesystem.private'),
            service('kernel'),
            service(KernelPluginLoader::class),
            service(CacheInvalidator::class),
            service(SourceResolver::class),
            service('parameter_bag'),
            service('event_dispatcher'),
        ]);

    // Requirement
    $services->set(RequirementsValidator::class)
        ->args([
            service('plugin.repository'),
            param('kernel.project_dir'),
        ]);

    $services->set(PluginFinder::class)
        ->args([
            service(PackageProvider::class),
        ]);

    $services->set(VersionSanitizer::class);

    $services->set(PluginCreateCommand::class)
        ->args([
            param('kernel.project_dir'),
            service(ScaffoldingCollector::class),
            service(ScaffoldingWriter::class),
            service(Filesystem::class),
            tagged_iterator('shopware.scaffold.generator'),
        ])
        ->tag('console.command');

    $services->set(ScaffoldingCollector::class)
        ->args([
            tagged_iterator('shopware.scaffold.generator'),
        ]);

    $services->set(ScaffoldingWriter::class)
        ->args([
            service(Filesystem::class),
        ]);

    $services->set(ComposerGenerator::class)
        ->tag('shopware.scaffold.generator');

    $services->set(PluginClassGenerator::class)
        ->tag('shopware.scaffold.generator');

    $services->set(TestsGenerator::class)
        ->tag('shopware.scaffold.generator');

    $services->set(CommandGenerator::class)
        ->tag('shopware.scaffold.generator');

    $services->set(ScheduledTaskGenerator::class)
        ->tag('shopware.scaffold.generator');

    $services->set(EventSubscriberGenerator::class)
        ->tag('shopware.scaffold.generator');

    $services->set(StorefrontControllerGenerator::class)
        ->tag('shopware.scaffold.generator');

    $services->set(StoreApiRouteGenerator::class)
        ->tag('shopware.scaffold.generator');

    $services->set(EntityGenerator::class)
        ->args([
            service(ClockInterface::class),
        ])
        ->tag('shopware.scaffold.generator');

    $services->set(ConfigGenerator::class)
        ->tag('shopware.scaffold.generator');

    $services->set(JavascriptPluginGenerator::class)
        ->tag('shopware.scaffold.generator');

    $services->set(AdminModuleGenerator::class)
        ->tag('shopware.scaffold.generator');

    $services->set(CustomFieldsetGenerator::class)
        ->tag('shopware.scaffold.generator');

    $services->set(GitignoreGenerator::class)
        ->tag('shopware.scaffold.generator');

    $services->set(PluginTelemetrySubscriber::class)
        ->args([
            service(Meter::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopware.telemetry.subscriber');
};
