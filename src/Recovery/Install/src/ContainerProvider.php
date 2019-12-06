<?php declare(strict_types=1);

namespace Shopware\Recovery\Install;

use Doctrine\DBAL\DriverManager;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Recovery\Common\DumpIterator;
use Shopware\Recovery\Common\HttpClient\CurlClient;
use Shopware\Recovery\Common\MigrationRuntime;
use Shopware\Recovery\Common\Service\JwtCertificateService;
use Shopware\Recovery\Common\Service\Notification;
use Shopware\Recovery\Common\Service\UniqueIdGenerator;
use Shopware\Recovery\Common\SystemLocker;
use Shopware\Recovery\Install\Service\ConfigWriter;
use Shopware\Recovery\Install\Service\DatabaseService;
use Shopware\Recovery\Install\Service\TranslationService;
use Shopware\Recovery\Install\Service\WebserverCheck;
use Slim\App;
use Slim\Views\PhpRenderer;

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
        $recoveryRoot = dirname(__DIR__, 2);
        $container['config'] = $this->config;
        $container['install.language'] = '';

        $container['shopware.version'] = static function () {
            return trim(file_get_contents(SW_PATH . '/public/recovery/install/data/version'));
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
            $selectedLanguage = $c->offsetGet('install.language') ?: 'en';

            return require __DIR__ . "/../data/lang/$selectedLanguage.php";
        };

        $container['translation.service'] = static function (Container $c) {
            return new TranslationService($c->offsetGet('translations'));
        };

        // dump class contains state so we define it as factory here
        $container['database.dump_iterator'] = $container->factory(static function () {
            $dumpFile = SW_PATH . '/vendor/shopware/core/schema.sql';

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

        $container['db'] = static function () {
            throw new \RuntimeException('Identifier DB not initialized yet');
        };

        $container['uniqueid.generator'] = static function () {
            return new UniqueIdGenerator(
                SW_PATH . '/.uniqueid.txt'
            );
        };

        $container['config.writer'] = static function ($c) {
            return new ConfigWriter(
                SW_PATH . '/.env',
                $c['uniqueid.generator']->getUniqueId()
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

        $container['migration.paths'] = static function () {
            $bundles = [
                'Core' => SW_PATH . '/vendor/shopware/core/Migration',
                'Storefront' => SW_PATH . '/vendor/shopware/storefront/Migration',
                'Elasticsearch' => SW_PATH . '/vendor/shopware/elasticsearch/Migration',
                'Administartion' => SW_PATH . '/vendor/shopware/administration/Migration',
            ];

            $paths = [];

            foreach ($bundles as $name => $path) {
                if (is_dir($path)) {
                    $paths[] = ['name' => $name, 'path' => $path];
                }
            }

            return $paths;
        };

        $container['migration.manager'] = static function ($c) {
            return new MigrationRuntime($c['dbal']);
        };

        $container['migration.collection'] = function ($c) {
            $paths = [];

            foreach ($c['migration.paths'] as $path) {
                $paths[sprintf('Shopware\\%s\\Migration', $path['name'])] = $path['path'];
            }

            return new MigrationCollection($paths);
        };

        $container['migration.collection.loader'] = static function ($c) {
            return new MigrationCollectionLoader($c['dbal'], $c['migration.collection']);
        };
    }
}
