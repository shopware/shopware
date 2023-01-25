<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'database:refresh-migration',
    description: 'Refreshes the migration state',
)]
#[Package('core')]
class RefreshMigrationCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Path to migration file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
        $filename = basename((string) $path);
        $className = pathinfo($filename, \PATHINFO_FILENAME);

        $output->writeln('Updating timestamp of migration: ' . $filename);

        if (!file_exists($path)) {
            throw new \RuntimeException('The provided migration file does not exist.');
        }

        $timestamp = $this->getCurrentTimestamp($filename);
        $newTimestamp = (string) time();

        $newPath = str_replace($timestamp, $newTimestamp, (string) $path);

        $search = [
            pathinfo($filename, \PATHINFO_FILENAME),
            'return ' . $timestamp . ';',
        ];

        $replace = [
            str_replace($timestamp, $newTimestamp, $className),
            'return ' . $newTimestamp . ';',
        ];

        $this->updateMigrationFile($path, $search, $replace);

        rename($path, $newPath);

        return self::SUCCESS;
    }

    private function getCurrentTimestamp(string $filename): string
    {
        if (!preg_match('#Migration([\d]+).*?\.php#i', $filename, $matches)) {
            throw new \RuntimeException('Could not determine current timestamp.');
        }

        return $matches[1];
    }

    private function updateMigrationFile(string $path, array $search, array $replace): void
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return;
        }

        $content = str_replace($search, $replace, $content);
        file_put_contents($path, $content);
    }
}
