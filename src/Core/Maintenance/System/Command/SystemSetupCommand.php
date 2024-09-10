<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Command;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Maintenance\MaintenanceException;
use Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Dotenv\Command\DotenvDumpCommand;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'system:setup',
    description: 'Setup the system',
)]
#[Package('core')]
class SystemSetupCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
        private readonly JwtCertificateGenerator $jwtCertificateGenerator,
        private readonly DotenvDumpCommand $dumpEnvCommand
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force setup and recreate everything')
            ->addOption('no-check-db-connection', null, InputOption::VALUE_NONE, 'dont check db connection')
            ->addOption('database-url', null, InputOption::VALUE_OPTIONAL, 'Database dsn', $this->getDefault('DATABASE_URL', ''))
            ->addOption('database-ssl-ca', null, InputOption::VALUE_OPTIONAL, 'Database SSL CA path', $this->getDefault('DATABASE_SSL_CA', ''))
            ->addOption('database-ssl-cert', null, InputOption::VALUE_OPTIONAL, 'Database SSL Cert path', $this->getDefault('DATABASE_SSL_CERT', ''))
            ->addOption('database-ssl-key', null, InputOption::VALUE_OPTIONAL, 'Database SSL Key path', $this->getDefault('DATABASE_SSL_KEY', ''))
            ->addOption('database-ssl-dont-verify-cert', null, InputOption::VALUE_OPTIONAL, 'Database Don\'t verify server cert', $this->getDefault('DATABASE_SSL_DONT_VERIFY_SERVER_CERT', ''))
            ->addOption('generate-jwt-keys', null, InputOption::VALUE_NONE, 'Generate jwt private and public key')
            ->addOption('jwt-passphrase', null, InputOption::VALUE_OPTIONAL, 'JWT private key passphrase', 'shopware')
            ->addOption('composer-home', null, InputOption::VALUE_REQUIRED, 'Set the composer home directory otherwise the environment variable $COMPOSER_HOME will be used or the project dir as fallback', $this->getDefault('COMPOSER_HOME', ''))
            ->addOption('app-env', null, InputOption::VALUE_OPTIONAL, 'Application environment', $this->getDefault('APP_ENV', 'prod'))
            ->addOption('app-url', null, InputOption::VALUE_OPTIONAL, 'Application URL', $this->getDefault('APP_URL', 'http://localhost'))
            ->addOption('blue-green', null, InputOption::VALUE_OPTIONAL, 'Blue green deployment', $this->getDefault('BLUE_GREEN_DEPLOYMENT', '1'))
            ->addOption('es-enabled', null, InputOption::VALUE_OPTIONAL, 'Elasticsearch enabled', $this->getDefault('SHOPWARE_ES_ENABLED', '0'))
            ->addOption('es-hosts', null, InputOption::VALUE_OPTIONAL, 'Elasticsearch Hosts', $this->getDefault('OPENSEARCH_URL', 'elasticsearch:9200'))
            ->addOption('es-indexing-enabled', null, InputOption::VALUE_OPTIONAL, 'Elasticsearch Indexing enabled', $this->getDefault('SHOPWARE_ES_INDEXING_ENABLED', '0'))
            ->addOption('es-index-prefix', null, InputOption::VALUE_OPTIONAL, 'Elasticsearch Index prefix', $this->getDefault('SHOPWARE_ES_INDEX_PREFIX', 'sw'))
            ->addOption('admin-es-hosts', null, InputOption::VALUE_OPTIONAL, 'Admin Elasticsearch Hosts', $this->getDefault('ADMIN_OPENSEARCH_URL', 'elasticsearch:9200'))
            ->addOption('admin-es-index-prefix', null, InputOption::VALUE_OPTIONAL, 'Admin Elasticsearch Index prefix', $this->getDefault('SHOPWARE_ADMIN_ES_INDEX_PREFIX', 'sw-admin'))
            ->addOption('admin-es-enabled', null, InputOption::VALUE_OPTIONAL, 'Admin Elasticsearch Enabled', $this->getDefault('SHOPWARE_ADMIN_ES_ENABLED', '0'))
            ->addOption('admin-es-refresh-indices', null, InputOption::VALUE_OPTIONAL, 'Admin Elasticsearch Refresh Indices', $this->getDefault('SHOPWARE_ADMIN_ES_REFRESH_INDICES', '0'))
            ->addOption('http-cache-enabled', null, InputOption::VALUE_OPTIONAL, 'Http-Cache enabled', $this->getDefault('SHOPWARE_HTTP_CACHE_ENABLED', '1'))
            ->addOption('http-cache-ttl', null, InputOption::VALUE_OPTIONAL, 'Http-Cache TTL', $this->getDefault('SHOPWARE_HTTP_DEFAULT_TTL', '7200'))
            ->addOption('cdn-strategy', null, InputOption::VALUE_OPTIONAL, 'CDN Strategy', $this->getDefault('SHOPWARE_CDN_STRATEGY_DEFAULT', 'id'))
            ->addOption('mailer-url', null, InputOption::VALUE_OPTIONAL, 'Mailer URL', $this->getDefault('MAILER_DSN', 'native://default'))
            ->addOption('dump-env', null, InputOption::VALUE_NONE, 'Dump the generated .env file in a optimized .env.local.php file, to skip parsing of the .env file on each request');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = [
            'APP_ENV' => $input->getOption('app-env'),
            'APP_URL' => trim((string) $input->getOption('app-url')),
            'DATABASE_URL' => $input->getOption('database-url'),
            'OPENSEARCH_URL' => $input->getOption('es-hosts'),
            'SHOPWARE_ES_ENABLED' => $input->getOption('es-enabled'),
            'SHOPWARE_ES_INDEXING_ENABLED' => $input->getOption('es-indexing-enabled'),
            'SHOPWARE_ES_INDEX_PREFIX' => $input->getOption('es-index-prefix'),
            'ADMIN_OPENSEARCH_URL' => $input->getOption('admin-es-hosts'),
            'SHOPWARE_ADMIN_ES_INDEX_PREFIX' => $input->getOption('admin-es-index-prefix'),
            'SHOPWARE_ADMIN_ES_ENABLED' => $input->getOption('admin-es-enabled'),
            'SHOPWARE_ADMIN_ES_REFRESH_INDICES' => $input->getOption('admin-es-refresh-indices'),
            'SHOPWARE_HTTP_CACHE_ENABLED' => $input->getOption('http-cache-enabled'),
            'SHOPWARE_HTTP_DEFAULT_TTL' => $input->getOption('http-cache-ttl'),
            'SHOPWARE_CDN_STRATEGY_DEFAULT' => $input->getOption('cdn-strategy'),
            'BLUE_GREEN_DEPLOYMENT' => $input->getOption('blue-green'),
            'MAILER_DSN' => $input->getOption('mailer-url'),
            'COMPOSER_HOME' => $input->getOption('composer-home'),
        ];

        if ($ca = $input->getOption('database-ssl-ca')) {
            $env['DATABASE_SSL_CA'] = $ca;
        }

        if ($cert = $input->getOption('database-ssl-cert')) {
            $env['DATABASE_SSL_CERT'] = $cert;
        }

        if ($certKey = $input->getOption('database-ssl-key')) {
            $env['DATABASE_SSL_KEY'] = $certKey;
        }

        if ($input->getOption('database-ssl-dont-verify-cert')) {
            $env['DATABASE_SSL_DONT_VERIFY_SERVER_CERT'] = '1';
        }

        if (empty($env['COMPOSER_HOME'])) {
            $env['COMPOSER_HOME'] = $this->projectDir . '/var/cache/composer';
        }

        $io = new SymfonyStyle($input, $output);

        if (file_exists($this->projectDir . '/symfony.lock')) {
            $io->warning('It looks like you have installed Shopware with Symfony Flex. You should use a .env.local file instead of creating a complete new one');
        }

        $io->title('Shopware setup process');
        $io->text('This tool will setup your instance.');

        if (!$input->getOption('force') && file_exists($this->projectDir . '/.env')) {
            $io->comment('Instance has already been set-up. To start over, please delete your .env file.');

            return Command::SUCCESS;
        }

        if (!$input->isInteractive()) {
            if (!Feature::isActive('v6.7.0.0')) {
                $this->generateJwt($input, $io);
            }

            $env['APP_SECRET'] = Random::getString(SystemGenerateAppSecretCommand::APP_SECRET_LENGTH);
            $env['INSTANCE_ID'] = $this->generateInstanceId();

            return $this->createEnvFile($input, $io, $env);
        }

        $io->section('Application information');
        $env['APP_ENV'] = $io->choice('Application environment', ['prod', 'dev'], $input->getOption('app-env'));

        // TODO: optionally check http connection (create test file in public and request)
        $validator = Validation::createCallable(new NotBlank(), new Url());
        $env['APP_URL'] = $io->ask('URL to your /public folder', $input->getOption('app-url'), $validator);

        $io->section('Application information');
        $env['BLUE_GREEN_DEPLOYMENT'] = $io->confirm('Blue Green Deployment', $input->getOption('blue-green') !== '0') ? '1' : '0';

        $io->section('Generate keys and secrets');

        if (!Feature::isActive('v6.7.0.0')) {
            $this->generateJwt($input, $io);
        }

        $env['APP_SECRET'] = Random::getString(SystemGenerateAppSecretCommand::APP_SECRET_LENGTH);
        $env['INSTANCE_ID'] = $this->generateInstanceId();

        $io->section('Database information');

        do {
            try {
                $exception = null;
                $env = [...$env, ...$this->getDsn($input, $io)];
            } catch (\Throwable $e) {
                $exception = $e;
                $io->error($exception->getMessage());
            }
        } while ($exception && $io->confirm('Retry?', false));

        if ($exception) {
            throw $exception;
        }

        return $this->createEnvFile($input, $io, $env);
    }

    /**
     * @return array<string, string>
     */
    private function getDsn(InputInterface $input, SymfonyStyle $io): array
    {
        $env = [];

        $emptyValidation = Validation::createCallable(new NotBlank());

        $dbUser = $io->ask('Database user', 'app', $emptyValidation);
        $dbPass = $io->askHidden('Database password') ?: '';
        $dbHost = $io->ask('Database host', 'localhost', $emptyValidation);
        $dbPort = $io->ask('Database port', '3306', $emptyValidation);
        $dbName = $io->ask('Database name', 'shopware', $emptyValidation);
        $dbSslCa = $io->ask('Database SSL CA Path', '');
        $dbSslCert = $io->ask('Database SSL Cert Path', '');
        $dbSslKey = $io->ask('Database SSL Key Path', '');
        $dbSslDontVerify = $io->askQuestion(new ConfirmationQuestion('Skip verification of the database server\'s SSL certificate?', false));

        $dsnWithoutDb = \sprintf(
            'mysql://%s:%s@%s:%d',
            (string) $dbUser,
            rawurlencode((string) $dbPass),
            (string) $dbHost,
            (int) $dbPort
        );
        $dsn = $dsnWithoutDb . '/' . $dbName;

        $params = ['url' => $dsnWithoutDb, 'charset' => 'utf8mb4'];

        if ($dbSslCa) {
            $params['driverOptions'][\PDO::MYSQL_ATTR_SSL_CA] = $dbSslCa;
            $env['DATABASE_SSL_CA'] = $dbSslCa;
        }

        if ($dbSslCert) {
            $params['driverOptions'][\PDO::MYSQL_ATTR_SSL_CERT] = $dbSslCert;
            $env['DATABASE_SSL_CERT'] = $dbSslCert;
        }

        if ($dbSslKey) {
            $params['driverOptions'][\PDO::MYSQL_ATTR_SSL_KEY] = $dbSslKey;
            $env['DATABASE_SSL_KEY'] = $dbSslKey;
        }

        if ($dbSslDontVerify) {
            $params['driverOptions'][\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            $env['DATABASE_SSL_DONT_VERIFY_SERVER_CERT'] = '1';
        }

        if (!$input->getOption('no-check-db-connection')) {
            $io->note('Checking database credentials');

            $connection = DriverManager::getConnection($params, new Configuration());
            $connection->executeStatement('SELECT 1');
        }

        $env['DATABASE_URL'] = $dsn;

        return $env;
    }

    /**
     * @param array<string, string|null> $configuration
     */
    private function createEnvFile(InputInterface $input, SymfonyStyle $output, array $configuration): int
    {
        $output->note('Preparing .env');

        $envVars = '';
        $envFile = $this->projectDir . '/.env';

        foreach ($configuration as $key => $value) {
            $envVars .= $key . '="' . str_replace('"', '\\"', (string) $value) . '"' . \PHP_EOL;
        }

        $output->text($envFile);
        $output->writeln('');
        $output->writeln($envVars);

        if ($input->isInteractive() && !$output->confirm('Check if everything is ok. Write into "' . $envFile . '"?', false)) {
            $output->error('Aborted!');

            return Command::FAILURE;
        }

        $output->note('Writing into ' . $envFile);

        file_put_contents($envFile, $envVars);

        if (!$input->getOption('dump-env')) {
            return Command::SUCCESS;
        }

        $application = $this->getApplication();
        if (!$application instanceof Application) {
            throw MaintenanceException::consoleApplicationNotFound();
        }

        return $application->doRun(
            new ArrayInput(
                [
                    'command' => $this->dumpEnvCommand->getName(),
                    'env' => $input->getOption('app-env'),
                ],
            ),
            $output
        );
    }

    private function generateJwt(InputInterface $input, OutputStyle $io): void
    {
        $jwtDir = $this->projectDir . '/config/jwt';

        if (!is_dir($jwtDir) && !mkdir($jwtDir, 0700, true) && !is_dir($jwtDir)) {
            throw MaintenanceException::couldNotCreateDirectory($jwtDir);
        }

        // TODO: make it regenerate the public key if only private exists
        if (file_exists($jwtDir . '/private.pem') && !$input->getOption('force')) {
            $io->note('Private/Public key already exists. Skipping');

            return;
        }

        if (!$input->getOption('generate-jwt-keys') && !$input->getOption('jwt-passphrase')) {
            return;
        }

        $this->jwtCertificateGenerator->generate(
            $jwtDir . '/private.pem',
            $jwtDir . '/public.pem',
            $input->getOption('jwt-passphrase')
        );
    }

    private function generateInstanceId(): string
    {
        return Random::getAlphanumericString(32);
    }

    private function getDefault(string $var, string $default): string
    {
        return (string) EnvironmentHelper::getVariable($var, $default);
    }
}
