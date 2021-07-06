<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\System\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SystemRestoreDatabaseCommand extends Command
{
    public static $defaultName = 'system:restore';

    private string $defaultDirectory;

    private Connection $connection;

    public function __construct(string $defaultDirectory, Connection $connection)
    {
        parent::__construct();
        $this->defaultDirectory = $defaultDirectory;
        $this->connection = $connection;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        system('mkdir -p ' . escapeshellarg($this->defaultDirectory));

        $dbName = $this->connection->getDatabase();
        $params = $this->connection->getParams();

        $path = sprintf('%s/%s_%s.sql', $this->defaultDirectory, $params['host'], $dbName);

        $cmd = sprintf(
            'mysql -u %s -p%s -h %s --port=%s %s < %s',
            escapeshellarg($params['user']),
            escapeshellarg($params['password']),
            escapeshellarg($params['host']),
            escapeshellarg((string) $params['port']),
            escapeshellarg($dbName),
            escapeshellarg($path)
        );

        $returnCode = 0;
        system($cmd, $returnCode);

        return $returnCode;
    }
}
