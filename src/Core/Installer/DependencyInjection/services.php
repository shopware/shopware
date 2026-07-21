<?php declare(strict_types=1);

namespace Shopware\Core\Installer\DependencyInjection;

use Composer\Composer;
use Composer\Repository\PlatformRepository;
use GuzzleHttp\Client;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Adapter\Asset\FallbackUrlPackage;
use Shopware\Core\Framework\Plugin\Composer\Factory;
use Shopware\Core\Installer\Configuration\AdminConfigurationService;
use Shopware\Core\Installer\Configuration\EnvConfigWriter;
use Shopware\Core\Installer\Configuration\ShopConfigurationService;
use Shopware\Core\Installer\Controller\DatabaseConfigurationController;
use Shopware\Core\Installer\Controller\DatabaseImportController;
use Shopware\Core\Installer\Controller\FinishController;
use Shopware\Core\Installer\Controller\LicenseController;
use Shopware\Core\Installer\Controller\RequirementsController;
use Shopware\Core\Installer\Controller\ShopConfigurationController;
use Shopware\Core\Installer\Controller\StartController;
use Shopware\Core\Installer\Controller\TranslationController;
use Shopware\Core\Installer\Database\BlueGreenDeploymentService;
use Shopware\Core\Installer\Database\DatabaseMigrator;
use Shopware\Core\Installer\Database\MigrationCollectionFactory;
use Shopware\Core\Installer\Finish\SystemLocker;
use Shopware\Core\Installer\Finish\UniqueIdGenerator;
use Shopware\Core\Installer\License\LicenseFetcher;
use Shopware\Core\Installer\Requirements\ConfigurationRequirementsValidator;
use Shopware\Core\Installer\Requirements\EnvironmentRequirementsValidator;
use Shopware\Core\Installer\Requirements\FilesystemRequirementsValidator;
use Shopware\Core\Installer\Requirements\IniConfigReader;
use Shopware\Core\Installer\Subscriber\InstallerLocaleListener;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Shopware\Core\System\Snippet\Service\AbstractTranslationConfigLoader;
use Shopware\Core\System\Snippet\Service\TranslationConfigLoader;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('shopware.installer.supportedLanguages', [
        'cs' => ['id' => 'cs-CZ', 'label' => 'Čeština'],
        'da-DK' => ['id' => 'da-DK', 'label' => 'Dansk'],
        'de' => ['id' => 'de-DE', 'label' => 'Deutsch'],
        'en-US' => ['id' => 'en-US', 'label' => 'English (US)'],
        'en' => ['id' => 'en-GB', 'label' => 'English (UK)'],
        'es-ES' => ['id' => 'es-ES', 'label' => 'Español'],
        'fr' => ['id' => 'fr-FR', 'label' => 'Français'],
        'it' => ['id' => 'it-IT', 'label' => 'Italiano'],
        'nl' => ['id' => 'nl-NL', 'label' => 'Nederlands'],
        'no' => ['id' => 'nn-NO', 'label' => 'Norsk'],
        'pl' => ['id' => 'pl-PL', 'label' => 'Język polski'],
        'pt-PT' => ['id' => 'pt-PT', 'label' => 'Português'],
        'sv-SE' => ['id' => 'sv-SE', 'label' => 'Svenska'],
    ]);

    $parameters->set('shopware.installer.supportedCurrencies', [
        'EUR' => 'EUR',
        'USD' => 'USD',
        'GBP' => 'GBP',
        'PLN' => 'PLN',
        'CHF' => 'CHF',
        'SEK' => 'SEK',
        'DKK' => 'DKK',
        'NOK' => 'NOK',
        'CZK' => 'CZK',
    ]);

    $parameters->set('shopware.installer.configurationPreselection', [
        'cs' => ['currency' => 'CZK'],
        'da-DK' => ['currency' => 'DKK'],
        'de' => ['currency' => 'EUR'],
        'en-US' => ['currency' => 'USD'],
        'en' => ['currency' => 'GBP'],
        'es-ES' => ['currency' => 'EUR'],
        'fr' => ['currency' => 'EUR'],
        'it' => ['currency' => 'EUR'],
        'nl' => ['currency' => 'EUR'],
        'no' => ['currency' => 'NOK'],
        'pl' => ['currency' => 'PLN'],
        'pt-PT' => ['currency' => 'EUR'],
        'sv-SE' => ['currency' => 'SEK'],
    ]);

    $parameters->set('shopware.installer.tosUrls', [
        'de' => 'https://api.shopware.com/gtc/de_DE.html',
        'en' => 'https://api.shopware.com/gtc/en_GB.html',
    ]);

    $parameters->set('env(SHOPWARE_ADMINISTRATION_PATH_NAME)', 'admin');

    $services = $containerConfigurator->services();

    $services->set('shopware.asset.asset', FallbackUrlPackage::class)
        ->args([
            [
                '',
            ],
            service('shopware.asset.version_strategy'),
        ])
        ->tag('assets.package', ['package' => 'asset']);

    $services->set('shopware.asset.version_strategy', EmptyVersionStrategy::class);

    $services->set(InstallerLocaleListener::class)
        ->args([
            param('shopware.installer.supportedLanguages'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(PlatformRepository::class);

    $services->set(Composer::class)
        ->factory([Factory::class, 'createComposer'])
        ->args([
            param('kernel.project_dir'),
        ]);

    $services->set(EnvironmentRequirementsValidator::class)
        ->args([
            service(Composer::class),
            service(PlatformRepository::class),
        ])
        ->tag('shopware.installer.requirement');

    $services->set(FilesystemRequirementsValidator::class)
        ->args([
            param('kernel.project_dir'),
        ])
        ->tag('shopware.installer.requirement');

    $services->set(ConfigurationRequirementsValidator::class)
        ->args([
            service(IniConfigReader::class),
        ])
        ->tag('shopware.installer.requirement');

    $services->set(IniConfigReader::class);

    $services->set('shopware.installer.guzzle', Client::class);

    $services->alias(AbstractTranslationConfigLoader::class, TranslationConfigLoader::class);

    $services->set(TranslationConfigLoader::class)
        ->args([
            service('filesystem'),
        ]);

    $services->set(TranslationConfig::class)
        ->lazy()
        ->public()
        ->factory([service(AbstractTranslationConfigLoader::class), 'load']);

    $services->set(LicenseFetcher::class)
        ->args([
            service('shopware.installer.guzzle'),
            param('shopware.installer.tosUrls'),
        ]);

    $services->set(StartController::class)
        ->public()
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(RequirementsController::class)
        ->public()
        ->args([
            tagged_iterator('shopware.installer.requirement'),
            param('kernel.project_dir'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(LicenseController::class)
        ->public()
        ->args([
            service(LicenseFetcher::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(DatabaseConfigurationController::class)
        ->public()
        ->args([
            service('translator'),
            service(BlueGreenDeploymentService::class),
            service(SetupDatabaseAdapter::class),
            service(DatabaseConnectionFactory::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(DatabaseImportController::class)
        ->public()
        ->args([
            service(DatabaseConnectionFactory::class),
            service(DatabaseMigrator::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(ShopConfigurationController::class)
        ->public()
        ->args([
            service(DatabaseConnectionFactory::class),
            service(EnvConfigWriter::class),
            service(ShopConfigurationService::class),
            service(AdminConfigurationService::class),
            service('translator'),
            service(TranslationConfig::class),
            param('shopware.installer.supportedLanguages'),
            param('shopware.installer.supportedCurrencies'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(FinishController::class)
        ->public()
        ->args([
            service(SystemLocker::class),
            service(Client::class),
            env('APP_URL')->string(),
            service(ClockInterface::class),
            env('SHOPWARE_ADMINISTRATION_PATH_NAME')->string(),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(BlueGreenDeploymentService::class);

    $services->set(SetupDatabaseAdapter::class);

    $services->set(DatabaseConnectionFactory::class);

    $services->set(DatabaseMigrator::class)
        ->args([
            service(SetupDatabaseAdapter::class),
            service(MigrationCollectionFactory::class),
            param('kernel.shopware_version'),
            service(IniConfigReader::class),
            service(ClockInterface::class),
        ]);

    $services->set(MigrationCollectionFactory::class)
        ->args([
            param('kernel.project_dir'),
        ]);

    $services->set(EnvConfigWriter::class)
        ->args([
            param('kernel.project_dir'),
            service(UniqueIdGenerator::class),
        ]);

    $services->set(ShopConfigurationService::class)
        ->args([
            service('event_dispatcher'),
            service(ClockInterface::class),
        ]);

    $services->set(AdminConfigurationService::class)
        ->args([
            service(ClockInterface::class),
        ]);

    $services->set(SystemLocker::class)
        ->args([
            param('kernel.project_dir'),
        ]);

    $services->set(UniqueIdGenerator::class)
        ->args([
            param('kernel.project_dir'),
        ]);

    $services->set(TranslationController::class)
        ->public()
        ->args([
            param('kernel.project_dir'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(Client::class);
};
