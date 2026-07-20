<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\System\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * @internal
 *
 * @phpstan-import-type Params from DriverManager
 * @phpstan-import-type OverrideParams from DriverManager
 */
#[AsCommand(
    name: 'system:dump',
    description: 'Dumps the database to a file',
)]
#[Package('framework')]
class SystemDumpDatabaseCommand extends Command
{
    /**
     * @var callable(list<string>): Process
     */
    private $processFactory;

    /**
     * @param callable(list<string>): Process|null $processFactory
     */
    public function __construct(
        private readonly string $defaultDirectory,
        private readonly Connection $connection,
        ?callable $processFactory = null,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        $this->processFactory = $processFactory ?? static fn (array $cmd): Process => new Process($cmd);
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ($this->processFactory)(['mkdir', '-p', $this->defaultDirectory])->mustRun();

        /** @var non-empty-string $dbName */
        $dbName = $this->connection->getDatabase();
        /** @var Params&OverrideParams $params */
        $params = $this->connection->getParams();

        $path = \sprintf('%s/%s_%s.sql', $this->defaultDirectory, $params['host'] ?? '', $dbName);

        $cmd = ['mysqldump',
            '-u', $params['user'] ?? '',
            '-h', $params['host'] ?? '',
            '--port=' . ($params['port'] ?? ''),
            '-q', '--opt', '--hex-blob', '--no-autocommit',
        ];

        if ($params['password'] ?? '') {
            $cmd[] = '-p' . $params['password'];
        }

        foreach (\array_filter((array) $input->getOption('ignore-table')) as $table) {
            $cmd[] = '--ignore-table=' . $dbName . '.' . $table;
        }

        $cmd[] = $dbName;

        $this->filesystem->dumpFile($path, 'SET unique_checks=0;SET foreign_key_checks=0;');

        $process = ($this->processFactory)($cmd);
        $process->mustRun();

        $this->filesystem->appendToFile($path, $process->getOutput());

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addOption('ignore-table', 'i', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Tables to ignore on export', ['enqueue', 'message_queue_stats', 'dead_message', 'increment', 'refresh_token']);
    }
}
