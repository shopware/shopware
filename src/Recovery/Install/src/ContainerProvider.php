<?php declare(strict_types=1);

namespace Shopware\Recovery\Install;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader as CoreMigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime as CoreMigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource as CoreMigrationSource;
use Shopware\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator;
use Shopware\Core\Maintenance\System\Service\ShopConfigurator;
use Shopware\Core\Maintenance\User\Service\UserProvisioner;
use Shopware\Recovery\Common\HttpClient\CurlClient;
use Shopware\Recovery\Common\Service\JwtCertificateService;
use Shopware\Recovery\Common\Service\Notification;
use Shopware\Recovery\Common\Service\UniqueIdGenerator;
use Shopware\Recovery\Common\SystemLocker;
use Shopware\Recovery\Install\Service\AdminService;
use Shopware\Recovery\Install\Service\BlueGreenDeploymentService;
use Shopware\Recovery\Install\Service\EnvConfigWriter;
use Shopware\Recovery\Install\Service\ShopService;
use Shopware\Recovery\Install\Service\TranslationService;
use Shopware\Recovery\Install\Service\WebserverCheck;
use Slim\App;
use Slim\Views\PhpRenderer;
use Symfony\Component\Dotenv\Dotenv;

class ContainerProvider implements ServiceProviderInterface
{
    /**
     * @var array
     */
    private $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        $recoveryRoot = \dirname(__DIR__, 2);
        $container['config'] = $this->config;
        $container['install.language'] = '';

        $container['shopware.version'] = static function ($c) {
            return $c['shopware.kernel']->getContainer()->getParameter('kernel.shopware_version');
        };

        $container['default.env.path'] = static function () {
            return SW_PATH . '/.env.defaults';
        };

        $container['default.env'] = static function ($c) {
            if (!is_readable($c['default.env.path'])) {
                return [];
            }

            return (new DotEnv())
                ->usePutenv(true)
                ->parse(file_get_contents($c['default.env.path']), $c['default.env.path']);
        };

        $container['env.path'] = static function () {
            return SW_PATH . '/.env';
        };

        $container['env.load'] = static function ($c) {
            $defaultPath = $c['default.env.path'];
            $path = $c['env.path'];

            return static function () use ($defaultPath, $path): void {
                if (is_readable((string) $defaultPath)) {
                    (new Dotenv())
                        ->usePutenv(true)
                        ->load((string) $defaultPath);
                }
                if (is_readable((string) $path)) {
                    (new Dotenv())
                        ->usePutenv(true)
                        ->load((string) $path);
                }
            };
        };

        $container['feature.isActive'] = static function ($c) {
            // load .env on first call
            $c['env.load']();

            return static function (string $featureName): bool {
                return Feature::isActive($featureName);
            };
        };

        $container['slim.app'] = static function ($c) {
            foreach ($c['config']['slim'] as $k => $v) {
                $c[$k] = $v;
            }

            return new App($c);
        };

        $container['renderer'] = static function ($c) {
            return new PhpRenderer($c['config']['slim']['templates.path']);
        };

        $container['system.locker'] = static function () {
            return new SystemLocker(
                SW_PATH . '/install.lock'
            );
        };

        $container['translations'] = static function (Container $c) {
            // load 'en' as fallback translation
            $fallbackTranslation = require __DIR__ . '/../data/lang/en.php';

            $selectedLanguage = $c->offsetGet('install.language') ?: 'en';
            $selectedTranslation = require __DIR__ . "/../data/lang/$selectedLanguage.php";

            return array_merge($fallbackTranslation, $selectedTranslation);
        };

        $container['translation.service'] = static function (Container $c) {
            return new TranslationService($c->offsetGet('translations'));
        };

        $container['http-client'] = static function () {
            return new CurlClient();
        };

        $container['install.requirements'] = static function ($c) use ($recoveryRoot) {
            return new Requirements($recoveryRoot . '/Common/requirements.php', $c['translation.service']);
        };

        $container['install.requirementsPath'] = static function () use ($recoveryRoot) {
            $check = new RequirementsPath(SW_PATH, $recoveryRoot . '/Common/requirements.php');

            return $check;
        };

        $container['uniqueid.generator'] = static function () {
            return new UniqueIdGenerator(
                SW_PATH . '/.uniqueid.txt'
            );
        };

        $container['config.writer'] = static function ($c) {
            return new EnvConfigWriter(
                SW_PATH . '/.env',
                $c['uniqueid.generator']->getUniqueId(),
                $c['default.env'],
                $c['shopware.kernel']
            );
        };

        $container['jwt_certificate.writer'] = static function () {
            return new JwtCertificateService(
                SW_PATH . '/config/jwt/',
                new JwtCertificateGenerator()
            );
        };

        $container['webserver.check'] = static function ($c) {
            return new WebserverCheck(
                $c['config']['check.ping_url'],
                $c['http-client']
            );
        };

        $container['menu.helper'] = static function ($c) {
            $routes = $c['config']['menu.helper']['routes'];

            return new MenuHelper(
                $c['slim.app'],
                $c['translation.service'],
                $routes
            );
        };

        $container['shopware.notify'] = static function ($c) {
            return new Notification(
                $c['config']['api.endpoint'],
                $c['uniqueid.generator']->getUniqueId(),
                $c['http-client'],
                $c['shopware.version']
            );
        };

        $container['dbal'] = static function (): void {
            throw new \RuntimeException('Identifier dbal not initialized yet');
        };

        $container['migration.sources'] = static function ($c) {
            return [
                new CoreMigrationSource('core', []),
                self::createMigrationSource('V6_3', true),
                self::createMigrationSource('V6_4', true),
                self::createMigrationSource('V6_5'),
            ];
        };

        $container['migration.runtime'] = static function ($c) {
            return new CoreMigrationRuntime($c['dbal'], new NullLogger());
        };

        $container['migration.collection.loader'] = static function ($c) {
            $sources = $c['migration.sources'];

            return new CoreMigrationCollectionLoader($c['dbal'], $c['migration.runtime'], $sources);
        };

        $container['blue.green.deployment.service'] = static function ($c) {
            return new BlueGreenDeploymentService($c['dbal']);
        };

        $container['shop.configurator'] = static function ($c) {
            return new ShopConfigurator($c['dbal']);
        };

        $container['shop.service'] = static function ($c) {
            return new ShopService($c['dbal'], $c['shop.configurator'], $c['shopware.kernel']->getContainer()->get(SalesChannelCreator::class));
        };

        $container['admin.service'] = static function ($c) {
            return new AdminService($c['dbal'], $c['shopware.kernel']->getContainer()->get(UserProvisioner::class));
        };
    }

    private static function createMigrationSource(string $version, bool $addReplacements = false): CoreMigrationSource
    {
        if (file_exists(SW_PATH . '/platform/src/Core/schema.sql')) {
            $coreBasePath = SW_PATH . '/platform/src/Core';
            $storefrontBasePath = SW_PATH . '/platform/src/Storefront';
        } elseif (file_exists(SW_PATH . '/src/Core/schema.sql')) {
            $coreBasePath = SW_PATH . '/src/Core';
            $storefrontBasePath = SW_PATH . '/src/Storefront';
        } else {
            $coreBasePath = SW_PATH . '/vendor/shopware/core';
            $storefrontBasePath = SW_PATH . '/vendor/shopware/storefront';
        }

        $hasStorefrontMigrations = is_dir($storefrontBasePath);

        $source = new CoreMigrationSource('core.' . $version, [
            sprintf('%s/Migration/%s', $coreBasePath, $version) => sprintf('Shopware\\Core\\Migration\\%s', $version),
        ]);

        if ($hasStorefrontMigrations) {
            $source->addDirectory(sprintf('%s/Migration/%s', $storefrontBasePath, $version), sprintf('Shopware\\Storefront\\Migration\\%s', $version));
        }

        if ($addReplacements) {
            $source->addReplacementPattern(sprintf('#^(Shopware\\\\Core\\\\Migration\\\\)%s\\\\([^\\\\]*)$#', $version), '$1$2');
            if ($hasStorefrontMigrations) {
                $source->addReplacementPattern(sprintf('#^(Shopware\\\\Storefront\\\\Migration\\\\)%s\\\\([^\\\\]*)$#', $version), '$1$2');
            }
        }

        return $source;
    }
}
