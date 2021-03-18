<?php declare(strict_types=1);

namespace Shopware\Recovery\Install;

use Doctrine\DBAL\DriverManager;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader as CoreMigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime as CoreMigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource as CoreMigrationSource;
use Shopware\Recovery\Common\DumpIterator;
use Shopware\Recovery\Common\HttpClient\CurlClient;
use Shopware\Recovery\Common\Service\JwtCertificateService;
use Shopware\Recovery\Common\Service\Notification;
use Shopware\Recovery\Common\Service\UniqueIdGenerator;
use Shopware\Recovery\Common\SystemLocker;
use Shopware\Recovery\Install\Service\BlueGreenDeploymentService;
use Shopware\Recovery\Install\Service\DatabaseService;
use Shopware\Recovery\Install\Service\EnvConfigWriter;
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

        $container['shopware.version'] = static function () {
            $version = null;
            $versionFile = SW_PATH . '/public/recovery/install/data/version';
            if (is_readable($versionFile)) {
                $version = file_get_contents($versionFile) ?: null;
            }

            return trim($version ?? '6.4.9999999.9999999-dev');
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

        // dump class contains state so we define it as factory here
        $container['database.dump_iterator'] = $container->factory(static function () {
            if (file_exists(SW_PATH . '/platform/src/Core/schema.sql')) {
                $dumpFile = SW_PATH . '/platform/src/Core/schema.sql';
            } else {
                $dumpFile = SW_PATH . '/vendor/shopware/core/schema.sql';
            }

            return new DumpIterator($dumpFile);
        });

        $container['http-client'] = static function () {
            return new CurlClient();
        };

        $container['install.requirements'] = static function ($c) use ($recoveryRoot) {
            return new Requirements($recoveryRoot . '/Common/requirements.php', $c['translation.service']);
        };

        $container['install.requirementsPath'] = static function () use ($recoveryRoot) {
            $check = new RequirementsPath(SW_PATH, $recoveryRoot . '/Common/requirements.php');
            $check->addFile('public/recovery/install/data');

            return $check;
        };

        $container['db'] = static function (): void {
            throw new \RuntimeException('Identifier DB not initialized yet');
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
                $c['default.env']
            );
        };

        $container['jwt_certificate.writer'] = static function () {
            return new JwtCertificateService(SW_PATH . '/config/jwt/');
        };

        $container['webserver.check'] = static function ($c) {
            return new WebserverCheck(
                $c['config']['check.ping_url'],
                $c['http-client']
            );
        };

        $container['database.service'] = static function ($c) {
            return new DatabaseService($c['db']);
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

        $container['dbal'] = static function ($c) {
            $options = [
                'pdo' => $c['db'],
                'driver' => 'pdo_mysql',
            ];

            return DriverManager::getConnection($options);
        };

        $container['migration.sources'] = static function ($c) {
            if (file_exists(SW_PATH . '/platform/src/Core/schema.sql')) {
                $coreBasePath = SW_PATH . '/platform/src/Core';
                $storefrontBasePath = SW_PATH . '/platform/src/Storefront';
            } else {
                $coreBasePath = SW_PATH . '/vendor/shopware/core';
                $storefrontBasePath = SW_PATH . '/vendor/shopware/storefront';
            }

            $v3 = new CoreMigrationSource('core.V6_3', [
                $coreBasePath . '/Migration/V6_3' => 'Shopware\\Core\\Migration\\V6_3',
                $storefrontBasePath . '/Migration/V6_3' => 'Shopware\\Storefront\\Migration\\V6_3',
            ]);
            $v3->addReplacementPattern('#^(Shopware\\\\Core\\\\Migration\\\\)V6_3\\\\([^\\\\]*)$#', '$1$2');
            $v3->addReplacementPattern('#^(Shopware\\\\Storefront\\\\Migration\\\\)V6_3\\\\([^\\\\]*)$#', '$1$2');

            $v4 = new CoreMigrationSource('core.V6_4', [
                $coreBasePath . '/Migration/V6_4' => 'Shopware\\Core\\Migration\\V6_4',
                $storefrontBasePath . '/Migration/V6_4' => 'Shopware\\Storefront\\Migration\\V6_4',
            ]);
            $v4->addReplacementPattern('#^(Shopware\\\\Core\\\\Migration\\\\)V6_4\\\\([^\\\\]*)$#', '$1$2');
            $v4->addReplacementPattern('#^(Shopware\\\\Storefront\\\\Migration\\\\)V6_4\\\\([^\\\\]*)$#', '$1$2');

            return [
                new CoreMigrationSource('core', []),
                $v3,
                $v4,
                new CoreMigrationSource('core.V6_5', [
                    $coreBasePath . '/Migration/V6_5' => 'Shopware\\Core\\Migration\\V6_5',
                    $storefrontBasePath . '/Migration/V6_5' => 'Shopware\\Storefront\\Migration\\V6_5',
                ]),
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
    }
}
