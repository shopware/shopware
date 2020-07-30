<?php declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Recovery\Common\Service\SystemConfigService;
use Shopware\Recovery\Update\DependencyInjection\Container;
use Shopware\Recovery\Update\Utils;

date_default_timezone_set('Europe/Berlin');
ini_set('display_errors', '1');
error_reporting(-1);

$config = require __DIR__ . '/../config/config.php';
$container = new Container(new \Slim\Container(), $config);

/** @var \Slim\App $app */
$app = $container->get('app');

$app->add(function (ServerRequestInterface $request, ResponseInterface $response, callable $next) use ($container, $app) {
    $baseUrl = \Shopware\Recovery\Common\Utils::getBaseUrl();

    $lang = null;
    if (!UPDATE_IS_MANUAL) {
        if (!is_file(UPDATE_META_FILE)) {
            $shopPath = str_replace('/recovery/update/', '/', $baseUrl);
            $shopPath = str_replace('/recovery/update', '/', $shopPath);

            return $response->withRedirect($shopPath);
        }

        $file = file_get_contents(UPDATE_META_FILE);
        $updateConfig = json_decode($file, true);
        $container->setParameter('update.config', $updateConfig);
        $lang = $updateConfig['locale'] ? mb_substr($updateConfig['locale'], 0, 2) : null;
    }

    session_set_cookie_params(7200, $baseUrl);

    // Silence errors during session start, Work around session_start(): ps_files_cleanup_dir: opendir(/var/lib/php5) failed: Permission denied (13)
    @session_start();
    @set_time_limit(0);

    // load 'en' as fallback translation
    $fallbackTranslation = require __DIR__ . '/../data/lang/en.php';

    $selectedLanguage = Utils::getLanguage($lang);
    $selectedTranslation = require __DIR__ . "/../data/lang/$selectedLanguage.php";

    $language = array_merge($fallbackTranslation, $selectedTranslation);

    $clientIp = Utils::getRealIpAddr();

    $viewVars = [];

    $viewVars['version'] = $container->get('shopware.version');
    $viewVars['app'] = $app;
    $viewVars['renderer'] = $container->get('renderer');
    $viewVars['router'] = $container->get('router');
    $viewVars['clientIp'] = $clientIp;
    $viewVars['baseUrl'] = $baseUrl;
    $viewVars['language'] = $language;
    $viewVars['selectedLanguage'] = $selectedLanguage;

    $container->get('renderer')->setAttributes($viewVars);

    return $next($request, $response);
});

$app->any('/', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    if (!UPDATE_IS_MANUAL) {
        return $response->withRedirect($container->get('router')->pathFor('checks'));
    }

    return $this->renderer->render($response, 'welcome.php', []);
})->setName('welcome');

// Check file & directory permissions
$app->any('/checks', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    return $container->get('controller.requirements')->checkRequirements($request, $response);
})->setName('checks');

$app->any('/dbmigration', function (ServerRequestInterface $request, ResponseInterface $response) {
    return $this->renderer->render($response, 'dbmigration.php', []);
})->setName('dbmigration');

$app->any('/applyMigrations', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    return $container->get('controller.batch')->applyMigrations($request, $response);
})->setName('applyMigrations');

$app->any('/unpack', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    return $container->get('controller.batch')->unpack($request, $response);
})->setName('unpack');

$app->any('/cleanup', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    return $container->get('controller.cleanup')->cleanupOldFiles($request, $response);
})->setName('cleanup');

$app->any('/clearCache', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    return $container->get('controller.cleanup')->deleteOutdatedFolders($request, $response);
})->setName('clearCache');

$app->any('/done', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    $container->get('shopware.update.chmod')->changePermissions($request, $response);

    $lastGeneratedVersionFile = SW_PATH . '/config/jwt/version';
    $lastGeneratedVersion = null;

    if (is_readable($lastGeneratedVersionFile)) {
        $lastGeneratedVersion = file_get_contents($lastGeneratedVersionFile);
    }

    $requiredVersion = '6.0.0 ea1.1';
    if (!$lastGeneratedVersion || version_compare($lastGeneratedVersion, $requiredVersion) === -1) {
        $jwtCertificateService = $container->get('jwt_certificate.writer');
        $jwtCertificateService->generate();
        file_put_contents($lastGeneratedVersionFile, $container->get('shopware.version'));
    }

    if (is_dir(SW_PATH . '/recovery/install')) {
        /** @var \Shopware\Recovery\Common\SystemLocker $systemLocker */
        $systemLocker = $container->get('system.locker');
        $systemLocker();
    }

    if (UPDATE_IS_MANUAL) {
        return $this->renderer->render($response, 'done_manual.php', []);
    }

    return $this->renderer->render($response, 'done.php', []);
})->setName('done');

$app->get('/finish', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    $baseUrl = \Shopware\Recovery\Common\Utils::getBaseUrl();
    $shopPath = str_replace('/recovery/update/', '/', $baseUrl);
    $shopPath = str_replace('/recovery/update', '/', $shopPath);

    $updateToken = bin2hex(random_bytes(16));
    /** @var SystemConfigService $systemConfig */
    $systemConfig = $container->get('system.config');
    $systemConfig->set('core.update.token', $updateToken);
    $redirectUrl = $shopPath . 'api/v3/_action/update/finish/' . $updateToken;

    if (UPDATE_META_FILE && file_exists(UPDATE_META_FILE)) {
        @unlink(UPDATE_META_FILE);
    }

    $assetsDir = SW_PATH . '/update-assets';
    if (is_dir($assetsDir)) {
        $di = new RecursiveDirectoryIterator($assetsDir, FilesystemIterator::SKIP_DOTS);
        $ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::CHILD_FIRST);
        /** @var SplFileInfo $file */
        foreach ($ri as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($assetsDir);
    }

    session_destroy();

    return $response->withRedirect($redirectUrl);
})->setName('finish');

return $app;
