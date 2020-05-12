<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Command;

use League\Flysystem\FilesystemInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class MediaPublishCommand extends Command
{
    protected static $defaultName = 'media:publish';

    /**
     * @var string
     */
    private $localPublicDirectory;

    /**
     * @var FilesystemInterface
     */
    private $publicFilesystem;

    public function __construct(FilesystemInterface $publicFilesystem, string $localPublicDirectory)
    {
        parent::__construct();
        $this->localPublicDirectory = $localPublicDirectory;
        $this->publicFilesystem = $publicFilesystem;
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('Upload all local media to remote filesystem');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directoriesToUpload = ['media', 'thumbnail'];

        foreach ($directoriesToUpload as $directory) {
            $files = $this->getFilesIteratorForFolder($directory);

            $output->writeln('Uploading files from ' . $directory);
            $progressBar = new ProgressBar($output);

            foreach ($progressBar->iterate($files) as $file) {
                $fs = fopen($file->getPathname(), 'rb');
                $this->publicFilesystem->putStream($directory . '/' . $file->getRelativePathname(), $fs);
                fclose($fs);
            }
            $progressBar->finish();
            $output->writeln('');
        }

        return 0;
    }

    private function getFilesIteratorForFolder(string $folderName): \Iterator
    {
        return Finder::create()
            ->ignoreDotFiles(false)
            ->files()
            ->in($this->localPublicDirectory . '/' . $folderName)
            ->getIterator();
    }
}
