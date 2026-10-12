<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Command;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

#[Package('framework')]
#[AsCommand(
    name: 'database:refresh-migration',
    description: 'Refreshes the migration state',
)]
class RefreshMigrationCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    /**
     * @throws IOException
     * @throws MigrationException
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Path to migration file')]
        string $path,
    ): int {
        if (!\is_file($path)) {
            throw MigrationException::migrationFileDoesNotExist($path);
        }

        $filename = basename($path);
        $className = pathinfo($filename, \PATHINFO_FILENAME);

        $io->writeln('Updating timestamp of migration: ' . $filename);

        $timestamp = $this->getCurrentTimestamp($filename);
        $newTimestamp = (string) $this->clock->now()->getTimestamp();

        $newPath = str_replace($timestamp, $newTimestamp, $path);

        $search = [
            pathinfo($filename, \PATHINFO_FILENAME),
            'return ' . $timestamp . ';',
        ];

        $replace = [
            str_replace($timestamp, $newTimestamp, $className),
            'return ' . $newTimestamp . ';',
        ];

        $this->updateMigrationFile($path, $search, $replace);

        $this->filesystem->rename($path, $newPath);

        return Command::SUCCESS;
    }

    private function getCurrentTimestamp(string $filename): string
    {
        if (!preg_match('/Migration(\d+).*?\.php$/i', $filename, $matches)) {
            throw MigrationException::couldNotDetermineTimestamp();
        }

        return $matches[1];
    }

    /**
     * @param list<string> $search
     * @param list<string> $replace
     *
     * @throws IOException
     */
    private function updateMigrationFile(string $path, array $search, array $replace): void
    {
        $content = $this->filesystem->readFile($path);

        $content = str_replace($search, $replace, $content);
        $this->filesystem->dumpFile($path, $content);
    }
}
