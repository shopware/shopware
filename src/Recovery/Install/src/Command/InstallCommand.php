<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Command;

use Pimple\Container;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Recovery\Common\DumpIterator;
use Shopware\Recovery\Common\IOHelper;
use Shopware\Recovery\Common\Service\JwtCertificateService;
use Shopware\Recovery\Common\Service\SystemConfigService;
use Shopware\Recovery\Install\DatabaseFactory;
use Shopware\Recovery\Install\DatabaseInteractor;
use Shopware\Recovery\Install\Service\AdminService;
use Shopware\Recovery\Install\Service\BlueGreenDeploymentService;
use Shopware\Recovery\Install\Service\DatabaseService;
use Shopware\Recovery\Install\Service\EnvConfigWriter;
use Shopware\Recovery\Install\Service\ShopService;
use Shopware\Recovery\Install\Service\WebserverCheck;
use Shopware\Recovery\Install\Struct\AdminUser;
use Shopware\Recovery\Install\Struct\Currency;
use Shopware\Recovery\Install\Struct\DatabaseConnectionInformation;
use Shopware\Recovery\Install\Struct\Locale;
use Shopware\Recovery\Install\Struct\Shop;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class InstallCommand extends Command
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var IOHelper
     */
    private $IOHelper;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('install');
        $this->setDescription('Installs and does the initial configuration of Shopware');

        $this->addDbOptions();
        $this->addShopOptions();
        $this->addAdminOptions();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->IOHelper = new IOHelper(
            $input,
            $output,
            $this->getHelper('question')
        );

        /** @var Container $container */
        $container = $this->container = $this->getApplication()->getContainer();
        $container->offsetSet('install.language', 'en');
        $container->offsetGet('shopware.notify')->doTrackEvent('Installer started');

        if ($this->IOHelper->isInteractive()) {
            $this->printStartMessage();
        }

        $this->checkRequirements($container);

        /** @var JwtCertificateService $jwtCertificateService */
        $jwtCertificateService = $container->offsetGet('jwt_certificate.writer');
        $jwtCertificateService->generate();

        $connectionInfo = new DatabaseConnectionInformation();
        $connectionInfo = $this->getConnectionInfoFromArgs($input, $connectionInfo);
        $connectionInfo = $this->getConnectionInfoFromInteractiveShell(
            $this->IOHelper,
            $connectionInfo
        );
        $dbName = $connectionInfo->databaseName;
        $connectionInfo->databaseName = null;

        $conn = $this->initDatabaseConnection($connectionInfo, $container);
        $databaseService = new DatabaseService($conn);
        $databaseService->createDatabase($dbName);

        $connectionInfo->databaseName = $dbName;
        $databaseService->selectDatabase($connectionInfo->databaseName);

        /** @var BlueGreenDeploymentService $blueGreenDeploymentService */
        $blueGreenDeploymentService = $container->offsetGet('blue.green.deployment.service');
        $blueGreenDeploymentService->setEnvironmentVariable();

        $skipImport = $databaseService->containsShopwareSchema()
            && $input->getOption('no-skip-import')
            && $this->shouldSkipImport();

        if (!$skipImport) {
            $this->importDatabase();
        }

        $shop = new Shop();
        $shop = $this->getShopInfoFromArgs($input, $shop);
        $shop = $this->getShopInfoFromInteractiveShell($shop);

        /** @var EnvConfigWriter $configWriter */
        $configWriter = $this->container->offsetGet('config.writer');
        $configWriter->writeConfig($connectionInfo, $shop);

        if ($this->IOHelper->isInteractive() && !$this->webserverCheck($container, $shop)) {
            $this->IOHelper->writeln('Could not verify');
            if (!$this->IOHelper->askConfirmation('Continue?')) {
                return self::FAILURE;
            }
        }

        $adminUser = new AdminUser();
        if (!$input->getOption('skip-admin-creation')) {
            $adminUser = $this->getAdminInfoFromArgs($input, $adminUser);
            $adminUser = $this->getAdminInfoFromInteractiveShell($adminUser);
        }

        $systemConfigService = new SystemConfigService($conn);
        $shopService = new ShopService($conn, $systemConfigService);
        $shopService->updateShop($shop);

        if (!$input->getOption('skip-admin-creation')) {
            $adminService = new AdminService($conn);
            $adminService->createAdmin($adminUser);
        }

        /** @var \Shopware\Recovery\Common\SystemLocker $systemLocker */
        $systemLocker = $this->container->offsetGet('system.locker');
        $systemLocker();

        $additionalInformation = [
            'method' => 'console',
        ];

        $container->offsetGet('shopware.notify')->doTrackEvent('Installer finished', $additionalInformation);

        if ($this->IOHelper->isInteractive()) {
            $this->IOHelper->writeln('<info>Shop successfully installed.</info>');
        }

        return self::SUCCESS;
    }

    protected function checkRequirements(Container $container): void
    {
        $shopwareSystemCheck = $container->offsetGet('install.requirements');
        $systemCheckResults = $shopwareSystemCheck->toArray();

        $hasError = false;

        foreach ($systemCheckResults['checks'] as $checkResult) {
            $status = $checkResult['status'];
            if ($status === 'ok') {
                continue;
            }

            if ($status === 'error') {
                $hasError = true;
            }

            $this->IOHelper->writeln(sprintf(
                '%s: %s %s%s required. %s',
                $status,
                $checkResult['group'],
                $checkResult['name'],
                $checkResult['required'] === '1' ? '' : (' ' . $checkResult['required']),
                $checkResult['notice']
            ));
        }

        // TODO: check paths

        if ($hasError) {
            exit(1);
        }
    }

    /**
     * @return bool
     */
    protected function webserverCheck(Container $container, Shop $shop)
    {
        /** @var WebserverCheck $webserverCheck */
        $webserverCheck = $container->offsetGet('webserver.check');
        $pingUrl = $webserverCheck->buildPingUrl($shop);

        try {
            $this->IOHelper->writeln('Checking ping to: ' . $pingUrl);
            $webserverCheck->checkPing($shop);
        } catch (\Exception $e) {
            $this->IOHelper->writeln('Could not verify web server' . $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @param string[] $locales
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    protected function askForAdminLocale($locales)
    {
        $question = new ChoiceQuestion('Please select your admin locale', $locales);
        $question->setErrorMessage('Locale %s is invalid.');

        $shopLocale = $this->IOHelper->ask($question);

        return $shopLocale;
    }

    /**
     * @param string[] $locales
     *
     * @return string
     */
    protected function askForShopShopLocale($locales, $default = null)
    {
        $question = new ChoiceQuestion('Please select your shop locale', $locales, $default);
        $question->setErrorMessage('Locale %s is invalid.');

        $shopLocale = $this->IOHelper->ask($question);

        return $shopLocale;
    }

    /**
     * @return AdminUser
     */
    protected function getAdminInfoFromArgs(InputInterface $input, AdminUser $adminUser)
    {
        $adminUser->username = $input->getOption('admin-username');
        $adminUser->email = $input->getOption('admin-email');
        $adminUser->password = $input->getOption('admin-password');
        $adminUser->locale = $input->getOption('admin-locale');
        $adminUser->firstName = $input->getOption('admin-firstname');
        $adminUser->lastName = $input->getOption('admin-lastname');

        if ($adminUser->locale && !\in_array($adminUser->locale, Locale::getValidLocales(), true)) {
            throw new \RuntimeException('Invalid admin-locale provided');
        }

        return $adminUser;
    }

    /**
     * @return AdminUser
     */
    protected function getAdminInfoFromInteractiveShell(AdminUser $adminUser)
    {
        if (!$this->IOHelper->isInteractive()) {
            return $adminUser;
        }
        $this->IOHelper->cls();
        $this->IOHelper->writeln('<info>=== Admin Information ===</info>');

        $question = new Question('Admin username (demo): ', 'demo');
        $adminUsername = $this->IOHelper->ask($question);

        $question = new Question('Admin first name (John): ', 'John');
        $adminFirstName = $this->IOHelper->ask($question);

        $question = new Question('Admin last name (Doe): ', 'Doe');
        $adminLastName = $this->IOHelper->ask($question);

        $question = new Question('Admin email (your.email@shop.com): ', 'your.email@shop.com');
        $adminEmail = $this->IOHelper->ask($question);

        $question = new Question('Admin password (demo): ', 'demo');
        $adminPassword = $this->IOHelper->ask($question);

        $adminLocale = $this->askForAdminLocale(Locale::getValidLocales());

        $adminUser->username = $adminUsername;
        $adminUser->email = $adminEmail;
        $adminUser->password = $adminPassword;
        $adminUser->locale = $adminLocale;
        $adminUser->firstName = $adminFirstName;
        $adminUser->lastName = $adminLastName;

        return $adminUser;
    }

    protected function getShopInfoFromInteractiveShell(Shop $shop): Shop
    {
        if (!$this->IOHelper->isInteractive()) {
            return $shop;
        }

        $this->IOHelper->cls();
        $this->IOHelper->writeln('<info>=== Shop Information ===</info>');

        $shop->locale = $this->askForShopShopLocale(Locale::getValidLocales(), $shop->locale);
        $shop->host = $this->IOHelper->ask(sprintf('Shop host (%s): ', $shop->host), $shop->host);
        $shop->basePath = $this->IOHelper->ask(sprintf('Shop base path (%s): ', $shop->basePath), $shop->basePath);
        $shop->name = $this->IOHelper->ask(sprintf('Shop name (%s): ', $shop->name), $shop->name);
        $shop->email = $this->IOHelper->ask(sprintf('Shop email (%s): ', $shop->email), $shop->email);

        $question = new ChoiceQuestion(
            sprintf('Shop currency (%s): ', $shop->currency),
            Currency::getValidCurrencies(),
            $shop->currency
        );
        $question->setErrorMessage('Currency %s is invalid.');
        $shop->currency = $this->IOHelper->ask($question);
        $shop->country = $this->IOHelper->ask(sprintf('Shop default country (%s): ', $shop->country), $shop->country);

        return $shop;
    }

    protected function getShopInfoFromArgs(InputInterface $input, Shop $shop): Shop
    {
        $shop->name = $input->getOption('shop-name');
        $shop->email = $input->getOption('shop-email');
        $shop->host = $input->getOption('shop-host');
        $shop->basePath = $input->getOption('shop-path');
        $shop->locale = $input->getOption('shop-locale');
        $shop->currency = $input->getOption('shop-currency');
        $shop->country = $input->getOption('shop-country');

        if ($shop->locale && !\in_array($shop->locale, Locale::getValidLocales(), true)) {
            throw new \RuntimeException('Invalid shop-locale provided');
        }

        return $shop;
    }

    protected function initDatabaseConnection(DatabaseConnectionInformation $connectionInfo, Container $container): \PDO
    {
        $databaseFactory = new DatabaseFactory();
        $conn = $databaseFactory->createPDOConnection($connectionInfo);
        $container->offsetSet('db', $conn);

        return $conn;
    }

    protected function shouldSkipImport(): bool
    {
        if (!$this->IOHelper->isInteractive()) {
            return true;
        }

        $question = new ConfirmationQuestion(
            'The database already contains shopware tables. Skip import? (yes/no) [yes]',
            true
        );
        $skipImport = $this->IOHelper->ask($question);

        return (bool) $skipImport;
    }

    protected function getConnectionInfoFromInteractiveShell(
        IOHelper $IOHelper,
        DatabaseConnectionInformation $connectionInfo
    ): DatabaseConnectionInformation {
        if (!$IOHelper->isInteractive()) {
            return $connectionInfo;
        }

        $IOHelper->writeln('<info>=== Database configuration ===</info>');
        $databaseInteractor = new DatabaseInteractor($IOHelper);

        $databaseConnectionInformation = $databaseInteractor->askDatabaseConnectionInformation(
            $connectionInfo
        );

        $databaseFactory = new DatabaseFactory();

        do {
            $pdo = null;

            try {
                $pdo = $databaseFactory->createPDOConnection($databaseConnectionInformation);
            } catch (\PDOException $e) {
                $IOHelper->writeln('');
                $IOHelper->writeln(sprintf('Got database error: %s', $e->getMessage()));
                $IOHelper->writeln('');

                $databaseConnectionInformation = $databaseInteractor->askDatabaseConnectionInformation(
                    $databaseConnectionInformation
                );
            }
        } while (!$pdo);

        $databaseService = new DatabaseService($pdo);

        $omitSchemas = ['information_schema', 'mysql', 'sys', 'performance_schema'];
        $databaseNames = $databaseService->getSchemas($omitSchemas);

        $defaultChoice = null;
        if ($connectionInfo->databaseName) {
            if (\in_array($connectionInfo->databaseName, $databaseNames, true)) {
                $defaultChoice = array_search($connectionInfo->databaseName, $databaseNames, true);
            }
        }

        $choices = $databaseNames;
        array_unshift($choices, '[create new database]');
        $question = new ChoiceQuestion('Please select your database', $choices, $defaultChoice);
        $question->setErrorMessage('Database %s is invalid.');
        $databaseName = $databaseInteractor->askQuestion($question);

        if ($databaseName === $choices[0]) {
            $databaseName = $databaseInteractor->createDatabase($pdo);
        }

        $databaseService->selectDatabase($databaseName);

        if (!$databaseInteractor->continueWithExistingTables($databaseName, $pdo)) {
            $IOHelper->writeln('Installation aborted.');

            exit;
        }

        $databaseConnectionInformation->databaseName = $databaseName;

        return $databaseConnectionInformation;
    }

    /**
     * @return DatabaseConnectionInformation
     */
    protected function getConnectionInfoFromArgs(InputInterface $input, DatabaseConnectionInformation $connectionInfo)
    {
        $connectionInfo->username = $input->getOption('db-user');
        $connectionInfo->hostname = $input->getOption('db-host');
        $connectionInfo->port = $input->getOption('db-port');
        $connectionInfo->databaseName = $input->getOption('db-name');
        $connectionInfo->socket = $input->getOption('db-socket');
        $connectionInfo->password = $input->getOption('db-password');
        $connectionInfo->sslCaPath = $input->getOption('db-ssl-ca');
        $connectionInfo->sslCertPath = $input->getOption('db-ssl-cert');
        $connectionInfo->sslCertKeyPath = $input->getOption('db-ssl-key');
        $connectionInfo->sslDontVerifyServerCert = $input->getOption('db-ssl-dont-verify-cert') === '1' ? true : false;

        return $connectionInfo;
    }

    private function addDbOptions(): void
    {
        $this
            ->addOption(
                'db-host',
                null,
                InputOption::VALUE_REQUIRED,
                'Database host',
                'localhost'
            )
            ->addOption(
                'db-port',
                null,
                InputOption::VALUE_REQUIRED,
                'Database port',
                '3306'
            )
            ->addOption(
                'db-socket',
                null,
                InputOption::VALUE_REQUIRED,
                'Database socket'
            )
            ->addOption(
                'db-user',
                null,
                InputOption::VALUE_REQUIRED,
                'Database user'
            )
            ->addOption(
                'db-password',
                null,
                InputOption::VALUE_REQUIRED,
                'Database password'
            )
            ->addOption(
                'db-name',
                null,
                InputOption::VALUE_REQUIRED,
                'Database name'
            )

            ->addOption(
                'no-skip-import',
                null,
                InputOption::VALUE_NONE,
                'Import database data even if a valid schema already exists'
            )

            ->addOption(
                'db-ssl-ca',
                null,
                InputOption::VALUE_OPTIONAL,
                'Database SSL CA path'
            )

            ->addOption(
                'db-ssl-cert',
                null,
                InputOption::VALUE_OPTIONAL,
                'Database SSL Cert path'
            )

            ->addOption(
                'db-ssl-key',
                null,
                InputOption::VALUE_OPTIONAL,
                'Database SSL Key path'
            )

            ->addOption(
                'db-ssl-dont-verify-cert',
                null,
                InputOption::VALUE_OPTIONAL,
                'Don\'t verify server cert'
            )
        ;
    }

    private function addShopOptions(): void
    {
        $this
            ->addOption(
                'shop-locale',
                null,
                InputOption::VALUE_REQUIRED,
                'Shop locale'
            )
            ->addOption(
                'shop-host',
                null,
                InputOption::VALUE_REQUIRED,
                'Shop host',
                'localhost'
            )
            ->addOption(
                'shop-path',
                null,
                InputOption::VALUE_REQUIRED,
                'Shop path'
            )
            ->addOption(
                'shop-name',
                null,
                InputOption::VALUE_REQUIRED,
                'Shop name',
                'Demo shop'
            )
            ->addOption(
                'shop-email',
                null,
                InputOption::VALUE_REQUIRED,
                'Shop email address',
                'your.email@shop.com'
            )
            ->addOption(
                'shop-currency',
                null,
                InputOption::VALUE_REQUIRED,
                'Shop currency'
            )
            ->addOption(
                'shop-country',
                null,
                InputOption::VALUE_REQUIRED,
                'Expects an ISO-3166 three-letter country-code. This parameter sets the default country for the default sales-channel.',
                'GBR'
            )
        ;
    }

    private function addAdminOptions(): void
    {
        $this
            ->addOption(
                'skip-admin-creation',
                null,
                InputOption::VALUE_NONE,
                'If provided, no admin user will be created.'
            )

            ->addOption(
                'admin-username',
                null,
                InputOption::VALUE_REQUIRED,
                'Administrator username'
            )
            ->addOption(
                'admin-password',
                null,
                InputOption::VALUE_REQUIRED,
                'Administrator password'
            )
            ->addOption(
                'admin-email',
                null,
                InputOption::VALUE_REQUIRED,
                'Administrator email address'
            )
            ->addOption(
                'admin-locale',
                null,
                InputOption::VALUE_REQUIRED,
                'Administrator locale'
            )
            ->addOption(
                'admin-firstname',
                null,
                InputOption::VALUE_REQUIRED,
                'Administrator firstname'
            )
            ->addOption(
                'admin-lastname',
                null,
                InputOption::VALUE_REQUIRED,
                'Administrator lastname'
            )
        ;
    }

    /**
     * @return Container
     */
    private function getContainer()
    {
        return $this->container;
    }

    /**
     * Import database.
     */
    private function importDatabase(): void
    {
        /** @var \PDO $conn */
        $conn = $this->getContainer()->offsetGet('db');

        $this->IOHelper->cls();
        $this->IOHelper->writeln('<info>=== Import Database ===</info>');

        $preSql = <<<'EOT'
SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;
';
EOT;
        $conn->query($preSql);

        /** @var DumpIterator $dump */
        $dump = $this->getContainer()->offsetGet('database.dump_iterator');
        $this->dumpProgress($conn, $dump);

        $this->runMigrations();

        $conn->query('SET FOREIGN_KEY_CHECKS = 1;');
    }

    private function dumpProgress(\PDO $conn, DumpIterator $dump): void
    {
        $totalCount = $dump->count();

        $progress = $this->IOHelper->createProgressBar($totalCount);
        $progress->setRedrawFrequency(20);
        $progress->start();

        foreach ($dump as $sql) {
            // Execute each query one by one
            // https://bugs.php.net/bug.php?id=61613
            $conn->exec($sql);
            $progress->advance();
        }
        $progress->finish();
        $this->IOHelper->writeln('');
    }

    private function runMigrations(): void
    {
        /** @var MigrationCollectionLoader $migrationCollectionLoader */
        $migrationCollectionLoader = $this->container->offsetGet('migration.collection.loader');

        $coreMigrations = $migrationCollectionLoader->collectAllForVersion(
            $this->container->offsetGet('shopware.version'),
            MigrationCollectionLoader::VERSION_SELECTION_ALL
        );

        $coreMigrations->sync();

        $total = \count($coreMigrations->getExecutableMigrations());

        $progress = $this->IOHelper->createProgressBar($total);
        $progress->setRedrawFrequency(20);
        $progress->start();

        foreach ($coreMigrations->migrateInSteps() as $null) {
            $coreMigrations->migrateDestructiveInPlace(null, 1);
            $progress->advance();
        }

        $progress->finish();
        $this->IOHelper->writeln('');
    }

    private function printStartMessage(): void
    {
        $version = $this->container->offsetGet('shopware.version');

        $this->IOHelper->cls();
        $this->IOHelper->printBanner();
        $this->IOHelper->writeln(sprintf('<info>Welcome to the Shopware %s installer</info>', $version));
        $this->IOHelper->writeln('');
        $this->IOHelper->ask(new Question('Press return to start installation.'));
        $this->IOHelper->cls();
    }
}
