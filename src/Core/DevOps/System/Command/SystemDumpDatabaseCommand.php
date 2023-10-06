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

/**
 * @psalm-import-type Params from DriverManager
 * @psalm-import-type OverrideParams from DriverManager
 */
#[AsCommand(
    name: 'system:dump',
    description: 'Dumps the database to a file',
)]
#[Package('core')]
class SystemDumpDatabaseCommand extends Command
{
    public function __construct(
        private readonly string $defaultDirectory,
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        system('mkdir -p ' . escapeshellarg($this->defaultDirectory));

        /** @var string $dbName */
        $dbName = $this->connection->getDatabase();
        /** @var Params&OverrideParams $params */
        $params = $this->connection->getParams();

        $path = sprintf('%s/%s_%s.sql', $this->defaultDirectory, $params['host'] ?? '', $dbName);

        $portString = '';
        if ($params['password'] ?? '') {
            $portString = '-p' . escapeshellarg($params['password']);
        }

        file_put_contents($path, 'SET unique_checks=0;SET foreign_key_checks=0;');
        $cmd = sprintf(
            'mysqldump -u %s %s -h %s --port=%s -q --opt --hex-blob --no-autocommit %s %s >> %s',
            escapeshellarg($params['user'] ?? ''),
            $portString,
            escapeshellarg($params['host'] ?? ''),
            escapeshellarg((string) ($params['port'] ?? '')),
            $this->getIgnoreTableStmt($input, $dbName),
            escapeshellarg($dbName),
            escapeshellarg($path)
        );

        $returnCode = 0;
        system($cmd, $returnCode);

        return $returnCode;
    }

    protected function configure(): void
    {
        $this->addOption('ignore-table', 'i', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Tables to ignore on export', ['enqueue', 'message_queue_stats', 'dead_message', 'increment', 'refresh_token']);
    }

    protected function getIgnoreTableStmt(InputInterface $input, string $dbName): string
    {
        $option = $input->getOption('ignore-table');

        if (!$option) {
            return '';
        }

        $ignorableTables = \array_filter($option);

        return \implode(' ', \array_map(
            static fn (string $table): string => '--ignore-table ' . \escapeshellarg($dbName . '.' . $table),
            $ignorableTables
        ));
    }
}
