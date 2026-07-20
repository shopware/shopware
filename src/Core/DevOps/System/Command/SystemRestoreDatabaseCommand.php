<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\System\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
    name: 'system:restore',
    description: 'Restores the database from a file',
)]
#[Package('framework')]
class SystemRestoreDatabaseCommand extends Command
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
        $this->filesystem->mkdir($this->defaultDirectory);

        /** @var non-empty-string $dbName */
        $dbName = $this->connection->getDatabase();
        /** @var Params&OverrideParams $params */
        $params = $this->connection->getParams();

        $path = \sprintf('%s/%s_%s.sql', $this->defaultDirectory, $params['host'] ?? '', $dbName);

        $cmd = [
            'mysql',
            '-u', $params['user'] ?? '',
            '-h', $params['host'] ?? '',
            '--port=' . ($params['port'] ?? ''),
        ];
        if ($params['password'] ?? '') {
            $cmd[] = '-p' . $params['password'];
        }
        $cmd[] = $dbName;

        $sqlContent = '';
        if ($this->filesystem->exists($path)) {
            $sqlContent = $this->filesystem->readFile($path);
        }

        $process = ($this->processFactory)($cmd);
        $process->setInput($sqlContent);
        $process->run();

        return $process->getExitCode() ?? 1;
    }
}
