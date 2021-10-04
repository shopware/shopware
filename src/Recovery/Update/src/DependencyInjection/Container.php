<?php declare(strict_types=1);

namespace Shopware\Recovery\Update\DependencyInjection;

use Doctrine\DBAL\DriverManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader as CoreMigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime as CoreMigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource as CoreMigrationSource;
use Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator;
use Shopware\Recovery\Common\DependencyInjection\Container as BaseContainer;
use Shopware\Recovery\Common\HttpClient\CurlClient;
use Shopware\Recovery\Common\Service\JwtCertificateService;
use Shopware\Recovery\Common\Service\SystemConfigService;
use Shopware\Recovery\Common\SystemLocker;
use Shopware\Recovery\Update\Cleanup;
use Shopware\Recovery\Update\CleanupFilesFinder;
use Shopware\Recovery\Update\Controller\BatchController;
use Shopware\Recovery\Update\Controller\CleanupController;
use Shopware\Recovery\Update\Controller\RequirementsController;
use Shopware\Recovery\Update\FilePermissionChanger;
use Shopware\Recovery\Update\FilesystemFactory;
use Shopware\Recovery\Update\PathBuilder;
use Shopware\Recovery\Update\StoreApi;
use Shopware\Recovery\Update\Utils;
use Slim\App;
use Slim\Views\PhpRenderer;
use Symfony\Component\Dotenv\Dotenv;

class Container extends BaseContainer
{
    public function setup(\Pimple\Container $container): void
    {
        $backupDir = SW_PATH . \DIRECTORY_SEPARATOR . 'files' . \DIRECTORY_SEPARATOR . 'backup' . \DIRECTORY_SEPARATOR . 'auto_update';

        $me = $this;

        $container['shopware.version'] = function () use ($me) {
            $version = trim(file_get_contents(UPDATE_ASSET_PATH . \DIRECTORY_SEPARATOR . 'version'));

            return $version;
        };

        $container['env.path'] = static function () {
            return SW_PATH . '/.env';
        };

        $container['default.env.path'] = static function () {
            return UPDATE_ASSET_PATH . '/.env.defaults';
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

        $container['db'] = function () {
            return Utils::getConnection(SW_PATH);
        };

        $container['dbal'] = function ($c) {
            $options = [
                'pdo' => $c['db'],
                'driver' => 'pdo_mysql',
            ];

            return DriverManager::getConnection($options);
        };

        $container['filesystem.factory'] = function () use ($me) {
            $updateConfig = $me->getParameter('update.config');
            $ftp = (isset($updateConfig['ftp_credentials'])) ? $updateConfig['ftp_credentials'] : [];

            return new FilesystemFactory(SW_PATH, $ftp);
        };

        $container['path.builder'] = function () use ($backupDir) {
            $baseDir = SW_PATH;
            $updateDir = UPDATE_FILES_PATH;

            return new PathBuilder($baseDir, $updateDir, $backupDir);
        };

        $container['migration.sources'] = static function ($c) {
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
            return new CoreMigrationCollectionLoader($c['dbal'], $c['migration.runtime'], $c['migration.sources']);
        };

        $container['app'] = function ($c) {
            foreach ($c['config']['slim'] as $k => $v) {
                $c[$k] = $v;
            }

            return new App($c);
        };

        $container['http-client'] = function () {
            return new CurlClient();
        };

        $container['store.api'] = function () use ($me) {
            return new StoreApi(
                $me->get('http-client'),
                $me->getParameter('storeapi.endpoint')
            );
        };

        $container['cleanup.files.finder'] = function () {
            return new CleanupFilesFinder(SW_PATH);
        };

        $container['system.locker'] = function () {
            return new SystemLocker(
                SW_PATH . \DIRECTORY_SEPARATOR . 'recovery' . \DIRECTORY_SEPARATOR . 'install' . \DIRECTORY_SEPARATOR . 'data' . \DIRECTORY_SEPARATOR . 'install.lock'
            );
        };

        $container['controller.batch'] = function () use ($me) {
            return new BatchController(
                $me
            );
        };

        $container['controller.requirements'] = function () use ($me) {
            return new RequirementsController(
                $me,
                $me->get('app')
            );
        };

        $container['controller.cleanup'] = function () use ($me, $backupDir) {
            return new CleanupController(
                $me->get('cleanup.files.finder'),
                $me->get('shopware.update.cleanup'),
                $me->get('app'),
                SW_PATH,
                $backupDir
            );
        };

        $container['shopware.update.cleanup'] = function ($container) use ($backupDir) {
            return new Cleanup(SW_PATH, $backupDir);
        };

        $container['shopware.update.chmod'] = function ($container) {
            return new FilePermissionChanger([
                ['chmod' => 0775, 'filePath' => SW_PATH . '/bin/console'],
            ]);
        };

        $container['renderer'] = function ($c) {
            return new PhpRenderer($c['config']['slim']['templates.path']);
        };

        $container['errorHandler'] = function ($c) {
            return static function (ServerRequestInterface $request, ResponseInterface $response, \Throwable $e) use ($c) {
                if (empty($request->getHeader('X-Requested-With'))) {
                    throw $e;
                }

                $data = [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ];

                return $response->withStatus(500)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode($data));
            };
        };

        $container['jwt_certificate.writer'] = static function () {
            return new JwtCertificateService(
                SW_PATH . '/config/jwt/',
                new JwtCertificateGenerator()
            );
        };

        $container['system.config'] = static function ($c) {
            return new SystemConfigService($c['dbal']);
        };
    }
}
