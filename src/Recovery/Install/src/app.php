<?php declare(strict_types=1);
use HansOtt\PSR7Cookies\SetCookie;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Recovery\Common\HttpClient\Client;
use Shopware\Recovery\Common\Service\JwtCertificateService;
use Shopware\Recovery\Common\Service\SystemConfigService;
use Shopware\Recovery\Common\Steps\ErrorResult;
use Shopware\Recovery\Common\Steps\FinishResult;
use Shopware\Recovery\Common\Steps\ResultMapper;
use Shopware\Recovery\Common\Steps\ValidResult;
use Shopware\Recovery\Common\Utils;
use Shopware\Recovery\Install\ContainerProvider;
use Shopware\Recovery\Install\DatabaseFactory;
use Shopware\Recovery\Install\Requirements;
use Shopware\Recovery\Install\RequirementsPath;
use Shopware\Recovery\Install\Service\AdminService;
use Shopware\Recovery\Install\Service\BlueGreenDeploymentService;
use Shopware\Recovery\Install\Service\DatabaseService;
use Shopware\Recovery\Install\Service\EnvConfigWriter;
use Shopware\Recovery\Install\Service\ShopService;
use Shopware\Recovery\Install\Struct\AdminUser;
use Shopware\Recovery\Install\Struct\DatabaseConnectionInformation;
use Shopware\Recovery\Install\Struct\Shop;
use Slim\Container;

if (empty($_SESSION)) {
    $sessionPath = str_replace('index.php', '', $_SERVER['SCRIPT_NAME']);

    if (!headers_sent()) {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            session_set_cookie_params(600, $sessionPath);
        }

        @session_start();
    }
}

$config = require __DIR__ . '/../config/production.php';
$container = new Container();
$container->register(new ContainerProvider($config));

/** @var \Slim\App $app */
$app = $container->offsetGet('slim.app');

if (!isset($_SESSION['parameters'])) {
    $_SESSION['parameters'] = [];
}

if (isset($_SESSION['databaseConnectionInfo'])) {
    $connectionInfo = $_SESSION['databaseConnectionInfo'];

    try {
        $databaseFactory = new DatabaseFactory();
        $connection = $databaseFactory->createPDOConnection($connectionInfo);

        // Init db in container
        $container->offsetSet('db', $connection);
    } catch (\Exception $e) {
        // Jump to form
        throw $e;
    }
}

$localeForLanguage = static function (string $language): string {
    switch (mb_strtolower($language)) {
        case 'de':
            return 'de-DE';
        case 'en':
            return 'en-GB';
        case 'nl':
            return 'nl-NL';
        case 'it':
            return 'it-IT';
        case 'fr':
            return 'fr-FR';
        case 'es':
            return 'es-ES';
        case 'pt':
            return 'pt-PT';
        case 'pl':
            return 'pl-PL';
        case 'sv':
            return 'sv-SE';
        case 'cs':
            return 'cs-CZ';
    }

    return mb_strtolower($language) . '-' . mb_strtoupper($language);
};

$app->add(function (ServerRequestInterface $request, ResponseInterface $response, callable $next) use ($container, $localeForLanguage) {
    // load .env and .env.defaults
    $container->offsetGet('env.load')();

    $selectLanguage = function (array $allowedLanguages): string {
        /**
         * Load language file
         */
        $selectedLanguage = 'de';
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $selectedLanguage = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            $selectedLanguage = mb_strtolower(mb_substr($selectedLanguage[0], 0, 2));
        }
        if (empty($selectedLanguage) || !in_array($selectedLanguage, $allowedLanguages, true)) {
            $selectedLanguage = 'en';
        }

        if (isset($_REQUEST['language']) && in_array($_REQUEST['language'], $allowedLanguages, true)) {
            $selectedLanguage = $_REQUEST['language'];

            if (isset($_SESSION['parameters']['c_config_shop_currency'])) {
                unset($_SESSION['parameters']['c_config_shop_currency']);
            }

            if (isset($_SESSION['parameters']['c_config_admin_language'])) {
                unset($_SESSION['parameters']['c_config_admin_language']);
            }
            $_SESSION['language'] = $selectedLanguage;

            return $selectedLanguage;
        }

        if (isset($_SESSION['language']) && in_array($_SESSION['language'], $allowedLanguages, true)) {
            $selectedLanguage = $_SESSION['language'];

            return $selectedLanguage;
        }
        $_SESSION['language'] = $selectedLanguage;

        return $selectedLanguage;
    };

    if ($request->getParsedBody()) {
        foreach ($request->getParsedBody() as $key => $value) {
            if (mb_strpos($key, 'c_') !== false) {
                $_SESSION['parameters'][$key] = $value;
            }
        }
    }

    $allowedLanguages = $container->offsetGet('config')['languages'];
    $selectedLanguage = $selectLanguage($allowedLanguages);

    $container->offsetSet('install.language', $selectedLanguage);

    $cookie = new SetCookie('installed-locale', $localeForLanguage($selectedLanguage), time() + 7200, '/');
    $cookie->addToResponse($response);

    $viewAttributes = [];

    $viewAttributes['version'] = $container->offsetGet('shopware.version');
    $viewAttributes['t'] = $container->offsetGet('translation.service');
    $viewAttributes['menuHelper'] = $container->offsetGet('menu.helper');
    $viewAttributes['languages'] = $allowedLanguages;
    $viewAttributes['languageIsos'] = array_map($localeForLanguage, $allowedLanguages);
    $viewAttributes['selectedLanguage'] = $selectedLanguage;
    $viewAttributes['translations'] = $container->offsetGet('translations');
    $viewAttributes['baseUrl'] = Utils::getBaseUrl();
    $viewAttributes['error'] = false;
    $viewAttributes['parameters'] = $_SESSION['parameters'];
    $viewAttributes['app'] = $container->offsetGet('slim.app');

    $container->offsetGet('renderer')->setAttributes($viewAttributes);

    return $next($request, $response);
});

$app->any('/', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    $menuHelper = $container->offsetGet('menu.helper');
    $menuHelper->setCurrent('language-selection');

    $container['shopware.notify']->doTrackEvent('Installer started');

    $viewVars = [
        'languages' => $container->offsetGet('config')['languages'],
    ];

    return $this->renderer->render($response, 'language-selection.php', $viewVars);
})->setName('language-selection');

$app->any('/requirements/', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    $menuHelper = $container->offsetGet('menu.helper');
    $menuHelper->setCurrent('requirements');

    // Check system requirements
    /** @var Requirements $shopwareSystemCheck */
    $shopwareSystemCheck = $container->offsetGet('install.requirements');
    $systemCheckResults = $shopwareSystemCheck->toArray();

    $viewAttributes = [];

    $viewAttributes['warning'] = (bool) $systemCheckResults['hasWarnings'];
    $viewAttributes['error'] = (bool) $systemCheckResults['hasErrors'];
    $viewAttributes['systemError'] = (bool) $systemCheckResults['hasErrors'];
    $viewAttributes['phpVersionNotSupported'] = $systemCheckResults['phpVersionNotSupported'];

    // Check file & directory permissions
    /** @var RequirementsPath $shopwareSystemCheckPath */
    $shopwareSystemCheckPath = $container->offsetGet('install.requirementsPath');
    $shopwareSystemCheckPathResult = $shopwareSystemCheckPath->check();

    $viewAttributes['pathError'] = false;

    if ($shopwareSystemCheckPathResult->hasError()) {
        $viewAttributes['error'] = true;
        $viewAttributes['pathError'] = true;
    }

    if ($request->getMethod() === 'POST' && $viewAttributes['error'] === false) {
        return $response->withRedirect($menuHelper->getNextUrl());
    }

    $viewAttributes['systemCheckResults'] = $systemCheckResults['checks'];
    $viewAttributes['systemCheckResultsWritePermissions'] = $shopwareSystemCheckPathResult->toArray();

    return $this->renderer->render($response, 'requirements.php', $viewAttributes);
})->setName('requirements');

$app->any('/license', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    $menuHelper = $container->offsetGet('menu.helper');
    $menuHelper->setCurrent('license');
    $viewAttributes = [];

    if ($request->getMethod() === 'POST') {
        $body = $request->getParsedBody();

        if (isset($body['tos'])) {
            return $response->withRedirect($menuHelper->getNextUrl());
        }

        $viewAttributes['error'] = true;
    }

    $tosUrls = $container->offsetGet('config')['tos.urls'];
    $tosUrl = $tosUrls['en'];

    if (array_key_exists($container->offsetGet('install.language'), $tosUrls)) {
        $tosUrl = $tosUrls[$container->offsetGet('install.language')];
    }
    $viewAttributes['tosUrl'] = $tosUrl;
    $viewAttributes['tos'] = '';

    /** @var Client $client */
    $client = $container->offsetGet('http-client');

    try {
        $tosResponse = $client->get($tosUrl);
        $viewAttributes['tos'] = $tosResponse->getBody();
        $viewAttributes['error'] = $tosResponse->getCode() >= 400;
    } catch (Exception $e) {
        $viewAttributes['error'] = $e->getMessage();
    }

    return $this->renderer->render($response, 'license.php', $viewAttributes);
})->setName('license');

$app->any('/database-configuration/', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    $menuHelper = $container->offsetGet('menu.helper');
    $menuHelper->setCurrent('database-configuration');

    /** @var \Shopware\Recovery\Install\Service\TranslationService $translationService */
    $translationService = $container->offsetGet('translation.service');

    if ($request->getMethod() !== 'POST') {
        return $this->renderer->render($response, 'database-configuration.php', []);
    }

    // Initiate database object
    $databaseParameters = [
        'user' => $_SESSION['parameters']['c_database_user'] ?? '',
        'password' => $_SESSION['parameters']['c_database_password'] ?? '',
        'host' => $_SESSION['parameters']['c_database_host'] ?? '',
        'port' => $_SESSION['parameters']['c_database_port'] ?? '',
        'socket' => $_SESSION['parameters']['c_database_socket'] ?? '',
        'database' => $_SESSION['parameters']['c_database_schema'] ?? '',
        'sslCaPath' => $_SESSION['parameters']['c_database_ssl_ca_path'] ?? '',
        'sslCertPath' => $_SESSION['parameters']['c_database_ssl_cert_path'] ?? '',
        'sslCertKeyPath' => $_SESSION['parameters']['c_database_ssl_cert_key_path'] ?? '',
        'sslDontVerifyCert' => $_SESSION['parameters']['c_database_ssl_dont_verify_cert'] ?? '0',
    ];

    if (empty($databaseParameters['user'])
        || empty($databaseParameters['host'])
        || empty($databaseParameters['port'])
        || empty($databaseParameters['database'])
    ) {
        return $this->renderer->render($response, 'database-configuration.php', [
            'error' => $translationService->t('database-configuration_error_required_fields'),
        ]);
    }

    $connectionInfo = new DatabaseConnectionInformation();
    $connectionInfo->username = $databaseParameters['user'];
    $connectionInfo->hostname = $databaseParameters['host'];
    $connectionInfo->port = $databaseParameters['port'];
    $connectionInfo->databaseName = $databaseParameters['database'];
    $connectionInfo->password = $databaseParameters['password'];
    $connectionInfo->socket = $databaseParameters['socket'];
    $connectionInfo->sslCaPath = $databaseParameters['sslCaPath'];
    $connectionInfo->sslCertPath = $databaseParameters['sslCertPath'];
    $connectionInfo->sslCertKeyPath = $databaseParameters['sslCertKeyPath'];
    $connectionInfo->sslDontVerifyServerCert = $databaseParameters['sslDontVerifyCert'] === '1';

    try {
        try {
            $databaseFactory = new DatabaseFactory();
            $connection = $databaseFactory->createPDOConnection($connectionInfo); // check connection
        } catch (\PDOException $e) {
            // Unknown database https://dev.mysql.com/doc/refman/8.0/en/server-error-reference.html#error_er_bad_db_error
            if ($e->getCode() !== 1049) {
                throw $e;
            }

            $connectionInfo->databaseName = '';
            $connection = $databaseFactory->createPDOConnection($connectionInfo);

            $service = new DatabaseService($connection);
            $service->createDatabase($databaseParameters['database']);
            $connectionInfo->databaseName = $databaseParameters['database'];

            $connection->exec('USE `' . $connectionInfo->databaseName . '`');
        }
    } catch (Exception $e) {
        return $this->renderer->render($response, 'database-configuration.php', ['error' => $e->getMessage()]);
    } finally {
        // Init db in container
        $container->offsetSet('db', $connection ?? null);
    }

    $_SESSION['databaseConnectionInfo'] = $connectionInfo;

    /** @var BlueGreenDeploymentService $blueGreenDeploymentService */
    $blueGreenDeploymentService = $container->offsetGet('blue.green.deployment.service');
    $blueGreenDeploymentService->setEnvironmentVariable();

    /** @var DatabaseService $databaseService */
    $databaseService = $container->offsetGet('database.service');

    if ($databaseService->hasTables($connectionInfo->databaseName)) {
        return $this->renderer->render($response, 'database-configuration.php', [
            'error' => $translationService->t('database-configuration_non_empty_database'),
        ]);
    }

    try {
        /** @var JwtCertificateService $jwtCertificateService */
        $jwtCertificateService = $container->offsetGet('jwt_certificate.writer');
        $jwtCertificateService->generate();
    } catch (\Exception $e) {
        return $this->renderer->render($response, 'database-configuration.php', ['error' => $e->getMessage()]);
    }

    return $response->withRedirect($menuHelper->getNextUrl());
})->setName('database-configuration');

$app->any('/database-import/', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    $menuHelper = $container->offsetGet('menu.helper');
    $menuHelper->setCurrent('database-import');

    /** @var \Shopware\Recovery\Install\Service\TranslationService $translationService */
    $translationService = $container->offsetGet('translation.service');

    if ($request->getMethod() === 'POST') {
        return $response->withRedirect($menuHelper->getNextUrl());
    }

    if (!isset($_SESSION[BlueGreenDeploymentService::ENV_NAME])) {
        $menuHelper->setCurrent('database-configuration');

        return $this->renderer->render($response, 'database-configuration.php', [
            'error' => $translationService->t('database-configuration_error_required_fields'),
        ]);
    }

    try {
        $container->offsetGet('db');
    } catch (\Exception $e) {
        $menuHelper->setCurrent('database-configuration');

        return $this->renderer->render($response, 'database-configuration.php', [
            'error' => $translationService->t('database-configuration_error_required_fields'),
        ]);
    }
    $container->offsetGet('renderer')->addAttribute('languages', []);

    return $this->renderer->render($response, 'database-import.php', []);
})->setName('database-import');

$app->any('/configuration/', function (ServerRequestInterface $request, ResponseInterface $response) use ($app, $container, $localeForLanguage) {
    $menuHelper = $container->offsetGet('menu.helper');
    $menuHelper->setCurrent('configuration');

    /** @var \Shopware\Recovery\Install\Service\TranslationService $translationService */
    $translationService = $container->offsetGet('translation.service');

    try {
        $db = $container->offsetGet('db');
    } catch (\Exception $e) {
        $menuHelper->setCurrent('database-configuration');

        return $this->renderer->render($response, 'database-configuration.php', [
            'error' => $translationService->t('database-configuration_error_required_fields'),
        ]);
    }

    // setting up a database connection
    $connection = (new DatabaseFactory())->createPDOConnection($_SESSION['databaseConnectionInfo']);

    // getting iso code of all countries
    $statement = $connection->prepare('SELECT iso3, iso FROM country');
    $statement->execute();

    // formatting string e.g. "en-GB" to "GB"
    $localeIsoCode = mb_substr($localeForLanguage($_SESSION['language']), -2, 2);

    // flattening array
    $countryIsos = array_map(function ($country) use ($localeIsoCode) {
        return [
            'iso3' => $country['iso3'],
            'default' => $country['iso'] === $localeIsoCode ? true : false,
        ];
    }, $statement->fetchAll());

    // make iso codes available for the select field
    $viewAttributes['countryIsos'] = $countryIsos;

    if ($request->getMethod() === 'POST') {
        $adminUser = new AdminUser([
            'email' => $_SESSION['parameters']['c_config_admin_email'],
            'username' => $_SESSION['parameters']['c_config_admin_username'],
            'firstName' => $_SESSION['parameters']['c_config_admin_firstName'],
            'lastName' => $_SESSION['parameters']['c_config_admin_lastName'],
            'password' => $_SESSION['parameters']['c_config_admin_password'],
            'locale' => $localeForLanguage($_SESSION['language']),
        ]);

        $shop = new Shop([
            'name' => $_SESSION['parameters']['c_config_shopName'],
            'locale' => $_SESSION['parameters']['c_config_shop_language'],
            'currency' => $_SESSION['parameters']['c_config_shop_currency'],
            'additionalCurrencies' => empty($_SESSION['parameters']['c_available_currencies']) ? null : $_SESSION['parameters']['c_available_currencies'],
            'country' => $_SESSION['parameters']['c_config_shop_country'],
            'email' => $_SESSION['parameters']['c_config_mail'],
            'host' => $_SERVER['HTTP_HOST'],
            'basePath' => str_replace('/recovery/install/index.php', '', $_SERVER['SCRIPT_NAME']),
        ]);

        $systemConfigService = new SystemConfigService($db);
        $shopService = new ShopService($db, $systemConfigService);
        $adminService = new AdminService($db);

        if (!isset($_SESSION[BlueGreenDeploymentService::ENV_NAME])) {
            $menuHelper->setCurrent('database-configuration');

            return $this->renderer->render($response, 'database-configuration.php', [
                'error' => $translationService->t('database-configuration_error_required_fields'),
            ]);
        }

        $_ENV[BlueGreenDeploymentService::ENV_NAME] = $_SESSION[BlueGreenDeploymentService::ENV_NAME];

        /** @var EnvConfigWriter $configWriter */
        $configWriter = $container->offsetGet('config.writer');
        $configWriter->writeConfig($_SESSION['databaseConnectionInfo'], $shop);

        $hasErrors = false;

        try {
            $shopService->updateShop($shop);
            $adminService->createAdmin($adminUser);
        } catch (\Exception $e) {
            $hasErrors = true;
            $viewAttributes['error'] = $e->getMessage() . "\n" . $e->getTraceAsString();
        }

        if (!$hasErrors) {
            return $response->withRedirect($app->getContainer()->get('router')->pathFor('finish'));
        }
    }

    $domain = $_SERVER['HTTP_HOST'];
    $basepath = str_replace('/recovery/install/index.php', '', $_SERVER['SCRIPT_NAME']);

    // Load shop-url
    $viewAttributes['shop'] = ['domain' => $domain, 'basepath' => $basepath];

    $selectedLanguage = $container->offsetGet('install.language');
    $locale = '';

    if ($selectedLanguage === 'en') {
        $locale = 'en-GB';
    } elseif ($selectedLanguage === 'de') {
        $locale = 'de-DE';
    }

    if (empty($_SESSION['parameters']['c_config_shop_language'])) {
        $_SESSION['parameters']['c_config_shop_language'] = $locale;
    }
    if (empty($_SESSION['parameters']['c_config_shop_currency'])) {
        $translationService = $container->offsetGet('translation.service');
        $_SESSION['parameters']['c_config_shop_currency'] = $translationService->translate('currency');
    }
    if (empty($_SESSION['parameters']['c_config_admin_language'])) {
        $_SESSION['parameters']['c_config_admin_language'] = $locale;
    }

    $viewAttributes['parameters'] = $_SESSION['parameters'];

    return $this->renderer->render($response, 'configuration.php', $viewAttributes);
})->setName('configuration');

$app->any('/finish/', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    $menuHelper = $container->offsetGet('menu.helper');
    $menuHelper->setCurrent('finish');

    $basepath = str_replace('/recovery/install/index.php', '', $_SERVER['SCRIPT_NAME']);

    /** @var \Shopware\Recovery\Common\SystemLocker $systemLocker */
    $systemLocker = $container->offsetGet('system.locker');
    $systemLocker();

    $additionalInformation = [
        'language' => $container->offsetGet('install.language'),
        'method' => 'installer',
    ];

    $container->offsetGet('shopware.notify')->doTrackEvent('Installer finished', $additionalInformation);

    $schema = 'http';
    // This is for supporting Apache 2.2
    if (array_key_exists('HTTPS', $_SERVER) && mb_strtolower($_SERVER['HTTPS']) === 'on') {
        $schema = 'https';
    }
    if (array_key_exists('REQUEST_SCHEME', $_SERVER)) {
        $schema = $_SERVER['REQUEST_SCHEME'];
    }

    $url = $schema . '://' . $_SERVER['HTTP_HOST'] . $basepath . '/api/oauth/token';
    $data = json_encode([
        'grant_type' => 'password',
        'client_id' => 'administration',
        'scopes' => 'write',
        'username' => $_SESSION['parameters']['c_config_admin_username'],
        'password' => $_SESSION['parameters']['c_config_admin_password'],
    ]);

    session_destroy();

    /** @var \Shopware\Recovery\Common\HttpClient\Client $client */
    $client = $container->offsetGet('http-client');
    $loginResponse = $client->post($url, $data, ['Content-Type: application/json']);

    $data = json_decode($loginResponse->getBody(), true);
    $loginTokenData = [
        'access' => $data['access_token'], 'refresh' => $data['refresh_token'], 'expiry' => $data['expires_in'],
    ];

    return $this->renderer->render(
        $response,
        'finish.php',
        [
            'url' => $schema . '://' . $_SERVER['HTTP_HOST'] . $basepath,
            'loginTokenData' => $loginTokenData,
            'basePath' => $basepath,
            'host' => explode(':', $_SERVER['HTTP_HOST'])[0],
        ]
    );
})->setName('finish');

$app->any('/database-import/importDatabase', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    $response = $response->withHeader('Content-Type', 'application/json')
        ->withStatus(200);

    /** @var MigrationCollectionLoader $migrationCollectionLoader */
    $migrationCollectionLoader = $container->offsetGet('migration.collection.loader');
    $_SERVER[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE] = true;

    $coreMigrations = $migrationCollectionLoader->collectAllForVersion(
        $container->offsetGet('shopware.version'),
        MigrationCollectionLoader::VERSION_SELECTION_ALL
    );

    $resultMapper = new ResultMapper();

    if (!isset($_SESSION[BlueGreenDeploymentService::ENV_NAME])) {
        return $response
            ->withStatus(500)
            ->write(json_encode($resultMapper->toExtJs(new ErrorResult('Session expired, please go back to database configuration.'))));
    }

    $_ENV[BlueGreenDeploymentService::ENV_NAME] = $_SESSION[BlueGreenDeploymentService::ENV_NAME];

    $parameters = $request->getParsedBody();

    $offset = isset($parameters['offset']) ? (int) $parameters['offset'] : 0;
    $total = isset($parameters['total']) ? (int) $parameters['total'] : 0;

    if ($offset === 0) {
        /** @var \PDO $db */
        $db = $container->offsetGet('db');

        /* @var \Shopware\Recovery\Common\DumpIterator $dumpIterator */
        foreach ($container['database.dump_iterator'] as $query) {
            try {
                $db->query($query);
            } catch (PDOException $e) {
                // ignore pdo errors
                continue;
            }
        }

        $coreMigrations->sync();
    }

    if (!$total) {
        $total = count($coreMigrations->getExecutableMigrations()) * 2;
    }

    try {
        $result = $coreMigrations->migrateInSteps(null, 1);

        if (iterator_count($result) === 1) {
            return $response->write(json_encode($resultMapper->toExtJs(new ValidResult($offset + 1, $total))));
        }
    } catch (\Throwable $e) {
        return $response
            ->withStatus(500)
            ->write(json_encode($resultMapper->toExtJs(new ErrorResult($e->getMessage(), $e))));
    }

    try {
        $result = $coreMigrations->migrateDestructiveInSteps(null, 1);

        if (iterator_count($result) === 1) {
            return $response->write(json_encode($resultMapper->toExtJs(new ValidResult($offset + 1, $total))));
        }
    } catch (\Throwable $e) {
        return $response
            ->withStatus(500)
            ->write(json_encode($resultMapper->toExtJs(new ErrorResult($e->getMessage(), $e))));
    }

    return $response->write(json_encode($resultMapper->toExtJs(new FinishResult($offset, $total))));
})->setName('applyMigrations');

$app->post('/check-database-connection', function (ServerRequestInterface $request, ResponseInterface $response) use ($container) {
    $postData = (array) $request->getParsedBody();

    $connectionInfo = new DatabaseConnectionInformation([
        'username' => $postData['c_database_user'],
        'hostname' => $postData['c_database_host'],
        'port' => $postData['c_database_port'],
        'password' => $postData['c_database_password'],
        'socket' => $postData['c_database_socket'],
        'sslCaPath' => $postData['c_database_ssl_ca_path'],
        'sslCertPath' => $postData['c_database_ssl_cert_path'],
        'sslCertKeyPath' => $postData['c_database_ssl_cert_key_path'],
        'sslDontVerifyServerCert' => isset($postData['c_database_ssl_dont_verify_cert']) ? true : false,
    ]);

    try {
        $connection = (new DatabaseFactory())->createPDOConnection($connectionInfo);
    } catch (\Exception $e) {
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus(200)
            ->write(json_encode([]));
    }

    // Init db in container
    $container->offsetSet('db', $connection);

    /** @var DatabaseService $databaseService */
    $databaseService = $container->offsetGet('database.service');

    // No need for listing the following schemas
    $omitSchemas = ['information_schema', 'performance_schema', 'sys', 'mysql'];
    $databaseNames = $databaseService->getSchemas($omitSchemas);

    $result = [];
    foreach ($databaseNames as $databaseName) {
        $result[] = [
            'value' => $databaseName,
            'display' => $databaseName,
            'hasTables' => $databaseService->hasTables($databaseName),
        ];
    }

    return $response->withHeader('Content-Type', 'application/json')
        ->withStatus(200)
        ->write(json_encode($result));
})->setName('database');

return $app;
