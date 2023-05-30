<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Administration\Administration;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'administration:delete-files-after-build',
    description: 'Deletes all unnecessary files of the administration after the build process.',
)]
#[Package('admin')]
class DeleteAdminFilesAfterBuildCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        $question = new ConfirmationQuestion('This will delete all files unnecessary to build the administration. Do you want to continue? (y/n)');

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('Command aborted!');

            return 0;
        }

        $adminDir = \dirname((string) (new \ReflectionClass(Administration::class))->getFileName());
        $output->writeln('Deleting unnecessary files of the administration after the build process...');
        $progressBar = new ProgressBar($output, 100);

        $finder = new Finder();

        // Find all files in Administration/Resources/app/administration/src/module except for de-DE.json and en-GB.json
        $finder->in($adminDir . '/Resources/app/administration/src/module')
            ->notName('de-DE.json')
            ->notName('en-GB.json')
            ->files();

        foreach ($finder as $file) {
            unlink($file->getRealPath());
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
        unlink($adminDir . '/Resources/app/administration/package-lock.json');
        $progressBar->advance(25);

        $this->removeDirectory($adminDir . '/Resources/app/administration/static');
        $this->removeDirectory($adminDir . '/Resources/app/administration/build');
        $this->removeDirectory($adminDir . '/Resources/app/administration/scripts');
        $this->removeDirectory($adminDir . '/Resources/app/administration/eslint-rules');
        $this->removeDirectory($adminDir . '/Resources/app/administration/test');
        $progressBar->advance(25);
        $progressBar->finish();

        $output->writeln('');
        $output->writeln('All unnecessary files of the administration after the build process have been deleted.');

        return 0;
    }

    private function deleteEmptyDirectories(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteEmptyDirectories($path);
            }
        }

        if (\count(scandir($dir) ?: []) === 2) {
            rmdir($dir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir) || str_contains('/snippet', $dir)) {
            return;
        }

        $files = scandir($dir);
        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
