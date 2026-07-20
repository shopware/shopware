<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Administration\Administration;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'administration:delete-files-after-build',
    description: 'Deletes all unnecessary files of the administration after the build process.',
)]
#[Package('framework')]
class DeleteAdminFilesAfterBuildCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly Filesystem $filesystem)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        if (!$io->confirm('This will delete all files unnecessary to build the administration. Do you want to continue?', false)) {
            $io->text('Command aborted!');

            return Command::SUCCESS;
        }

        $adminDir = \dirname((string) (new \ReflectionClass(Administration::class))->getFileName());
        $output->writeln('Deleting unnecessary files of the administration after the build process...');
        $progressBar = new ProgressBar($output, 100);

        // Delete all module files except for de-DE.json and en-GB.json
        $finder = new Finder();
        $finder->in($adminDir . '/Resources/app/administration/src/module')
            ->notName('de-DE.json')
            ->notName('en-GB.json')
            ->files();

        foreach ($finder as $file) {
            $this->filesystem->remove($file->getRealPath());
        }
        $progressBar->advance(25);

        $this->deleteEmptyDirectories($adminDir . '/Resources/app/administration/src/module');
        $progressBar->advance(25);

        // Find all the following directories and files and delete them
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/app/adapter');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/app/assets');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/app/asyncComponent');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/app/component');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/app/decorator');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/app/directive');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/app/filter');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/app/init');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/app/init-post');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/app/init-pre');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/app/mixin');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/app/plugin');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/app/route');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/app/service');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/app/state');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/core');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/meta');
        $this->removeDirectory($adminDir . '/Resources/app/administration/src/scripts');
        $this->removeDirectory($adminDir . '/Resources/app/administration/patches');
        $this->filesystem->remove($adminDir . '/Resources/app/administration/package-lock.json');
        $progressBar->advance(25);

        $this->removeDirectory($adminDir . '/Resources/app/administration/static');
        $this->removeDirectory($adminDir . '/Resources/app/administration/build');
        $this->removeDirectory($adminDir . '/Resources/app/administration/scripts');
        $this->removeDirectory($adminDir . '/Resources/app/administration/eslint-rules');
        $this->removeDirectory($adminDir . '/Resources/app/administration/test');
        $progressBar->advance(25);
        $progressBar->finish();

        $io->newLine();
        $io->text('All unnecessary files of the administration after the build process have been deleted.');

        return Command::SUCCESS;
    }

    /**
     * Recursively deletes empty directories.
     */
    private function deleteEmptyDirectories(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        try {
            $finder = new Finder();
            $finder->in($dir)->directories()->depth(0);

            foreach ($finder as $subDir) {
                $this->deleteEmptyDirectories($subDir->getRealPath());
            }

            // Check if directory is empty after processing subdirectories
            $checkFinder = new Finder();
            $checkFinder->in($dir)->depth(0);

            if ($checkFinder->count() === 0) {
                $this->filesystem->remove($dir);
            }
        } catch (\UnexpectedValueException) {
            // Directory is not readable or accessible
            return;
        }
    }

    /**
     * Recursively deletes a directory and all its contents.
     * Prevents deletion of directories containing '/snippet' in their path.
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir) || str_contains($dir, '/snippet')) {
            return;
        }

        try {
            $finder = new Finder();
            $finder->in($dir)->depth(0);

            foreach ($finder as $item) {
                if ($item->isDir()) {
                    $this->removeDirectory($item->getRealPath());
                } else {
                    $this->filesystem->remove($item->getRealPath());
                }
            }

            $this->filesystem->remove($dir);
        } catch (\UnexpectedValueException) {
            // Directory is not readable or accessible
            return;
        }
    }
}
