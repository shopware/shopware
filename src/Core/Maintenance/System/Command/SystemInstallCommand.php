<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Command;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'system:install',
    description: 'Installs the Shopware 6 system',
)]
#[Package('core')]
class SystemInstallCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
        private readonly SetupDatabaseAdapter $setupDatabaseAdapter,
        private readonly DatabaseConnectionFactory $databaseConnectionFactory
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('create-database', null, InputOption::VALUE_NONE, 'Create database if it doesn\'t exist.')
            ->addOption('drop-database', null, InputOption::VALUE_NONE, 'Drop existing database')
            ->addOption('basic-setup', null, InputOption::VALUE_NONE, 'Create storefront sales channel and admin user')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force install even if install.lock exists')
            ->addOption('no-assign-theme', null, InputOption::VALUE_NONE, 'Do not assign the default theme')
            ->addOption('shop-name', null, InputOption::VALUE_REQUIRED, 'The name of your shop')
            ->addOption('shop-email', null, InputOption::VALUE_REQUIRED, 'Shop email address')
            ->addOption('shop-locale', null, InputOption::VALUE_REQUIRED, 'Default language locale of the shop')
            ->addOption('shop-currency', null, InputOption::VALUE_REQUIRED, 'Iso code for the default currency of the shop')
            ->addOption('skip-jwt-keys-generation', null, InputOption::VALUE_NONE, 'Skips generation of jwt private and public key')
            ->addOption('skip-assets-install', null, InputOption::VALUE_NONE, 'Skips installing of assets')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new ShopwareStyle($input, $output);

        // set default
        $isBlueGreen = EnvironmentHelper::getVariable('BLUE_GREEN_DEPLOYMENT', '1');
        $_ENV['BLUE_GREEN_DEPLOYMENT'] = $_SERVER['BLUE_GREEN_DEPLOYMENT'] = $isBlueGreen;
        putenv('BLUE_GREEN_DEPLOYMENT=' . $isBlueGreen);

        if (!$input->getOption('force') && file_exists($this->projectDir . '/install.lock')) {
            $output->comment('install.lock already exists. Delete it or pass --force to do it anyway.');

            return self::FAILURE;
        }

        $this->initializeDatabase($output, $input);

        $commands = [
            [
                'command' => 'database:migrate',
                'identifier' => 'core',
                '--all' => true,
            ],
            [
                'command' => 'database:migrate-destructive',
                'identifier' => 'core',
                '--all' => true,
                '--version-selection-mode' => 'all',
            ],
            [
                'command' => 'system:configure-shop',
                '--shop-name' => $input->getOption('shop-name'),
                '--shop-email' => $input->getOption('shop-email'),
                '--shop-locale' => $input->getOption('shop-locale'),
                '--shop-currency' => $input->getOption('shop-currency'),
                '--no-interaction' => true,
            ],
            [
                'command' => 'dal:refresh:index',
            ],
            [
                'command' => 'scheduled-task:register',
            ],
            [
                'command' => 'plugin:refresh',
            ],
        ];

        if (!$input->getOption('skip-jwt-keys-generation')) {
            array_unshift(
                $commands,
                [
                    'command' => 'system:generate-jwt',
                    'allowedToFail' => true,
                ]
            );
        }

        /** @var Application $application */
        $application = $this->getApplication();
        if ($application->has('theme:refresh')) {
            $commands[] = [
                'command' => 'theme:refresh',
            ];
        }

        if ($application->has('theme:compile')) {
            $commands[] = [
                'command' => 'theme:compile',
                'allowedToFail' => true,
            ];
        }

        if ($input->getOption('basic-setup')) {
            $commands[] = [
                'command' => 'user:create',
                'username' => 'admin',
                '--admin' => true,
                '--password' => 'shopware',
            ];

            if ($application->has('sales-channel:create:storefront')) {
                $commands[] = [
                    'command' => 'sales-channel:create:storefront',
                    '--name' => $input->getOption('shop-name') ?? 'Storefront',
                    '--url' => (string) EnvironmentHelper::getVariable('APP_URL', 'http://localhost'),
                    '--isoCode' => $input->getOption('shop-locale') ?? 'en-GB',
                ];
            }

            if ($application->has('theme:change') && !$input->getOption('no-assign-theme')) {
                $commands[] = [
                    'command' => 'theme:change',
                    'allowedToFail' => true,
                    '--all' => true,
                    'theme-name' => 'Storefront',
                ];
            }
        }

        if (!$input->getOption('skip-assets-install')) {
            $commands[] = [
                'command' => 'assets:install',
            ];
        }

        $commands[] = [
            'command' => 'cache:clear',
        ];

        $this->runCommands($commands, $output);

        if (!file_exists($this->projectDir . '/public/.htaccess')
            && file_exists($this->projectDir . '/public/.htaccess.dist')
        ) {
            copy($this->projectDir . '/public/.htaccess.dist', $this->projectDir . '/public/.htaccess');
        }

        touch($this->projectDir . '/install.lock');

        return self::SUCCESS;
    }

    /**
     * @param array<int, array<string, string|bool|null>> $commands
     */
    private function runCommands(array $commands, OutputInterface $output): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            throw new \RuntimeException('No application initialised');
        }

        foreach ($commands as $parameters) {
            // remove params with null value
            $parameters = array_filter($parameters);

            $output->writeln('');

            $command = $application->find((string) $parameters['command']);
            $allowedToFail = $parameters['allowedToFail'] ?? false;
            unset($parameters['command'], $parameters['allowedToFail']);

            try {
                $returnCode = $command->run(new ArrayInput($parameters, $command->getDefinition()), $output);
                if ($returnCode !== 0 && !$allowedToFail) {
                    return $returnCode;
                }
            } catch (\Throwable $e) {
                if (!$allowedToFail) {
                    throw $e;
                }
            }
        }

        return self::SUCCESS;
    }

    private function initializeDatabase(ShopwareStyle $output, InputInterface $input): void
    {
        $databaseConnectionInformation = DatabaseConnectionInformation::fromEnv();

        $connection = $this->databaseConnectionFactory->getConnection($databaseConnectionInformation, true);

        $output->writeln('Prepare installation');
        $output->writeln('');

        $dropDatabase = $input->getOption('drop-database');
        if ($dropDatabase) {
            $this->setupDatabaseAdapter->dropDatabase($connection, $databaseConnectionInformation->getDatabaseName());
            $output->writeln('Drop database `' . $databaseConnectionInformation->getDatabaseName() . '`');
        }

        $createDatabase = $input->getOption('create-database') || $dropDatabase;
        if ($createDatabase) {
            $this->setupDatabaseAdapter->createDatabase($connection, $databaseConnectionInformation->getDatabaseName());
            $output->writeln('Created database `' . $databaseConnectionInformation->getDatabaseName() . '`');
        }

        $importedBaseSchema = $this->setupDatabaseAdapter->initializeShopwareDb($connection, $databaseConnectionInformation->getDatabaseName());

        if ($importedBaseSchema) {
            $output->writeln('Imported base schema.sql');
        }

        $output->writeln('');
    }
}
