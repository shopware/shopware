<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Command;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 's3:set-visibility',
    description: 'Sets the visibility of all files in the s3 filesystem to public',
)]
#[Package('core')]
class S3FilesystemVisibilityCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FilesystemOperator $filesystemPrivate,
        private readonly FilesystemOperator $filesystemPublic,
        private readonly FilesystemOperator $filesystemTheme,
        private readonly FilesystemOperator $filesystemSitemap,
        private readonly FilesystemOperator $filesystemAsset
    ) {
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
        $style = new ShopwareStyle($input, $output);

        $style->warning('If both private and public objects are stored in the same bucket, this command will set all of them public.');
        $continue = $style->confirm('Continue?');

        if (!$continue) {
            return (int) Command::SUCCESS;
        }

        $style->comment('Setting visibility to private in private bucket.');
        $this->setVisibility($this->filesystemPrivate, $style, 'private');
        $style->comment('Setting visibility to public in public bucket.');
        $this->setVisibility($this->filesystemPublic, $style, 'public');
        $style->comment('Setting visibility to public in theme bucket.');
        $this->setVisibility($this->filesystemTheme, $style, 'public');
        $style->comment('Setting visibility to public in sitemap bucket.');
        $this->setVisibility($this->filesystemSitemap, $style, 'public');
        $style->comment('Setting visibility to public in asset bucket.');
        $this->setVisibility($this->filesystemAsset, $style, 'public');

        $style->info('Finished setting visibility of objects in all pre-defined buckets.');

        return Command::SUCCESS;
    }

    private function setVisibility(FilesystemOperator $filesystem, ShopwareStyle $style, string $visibility): void
    {
        $files = array_filter($filesystem->listContents('/', true)->toArray(), fn (StorageAttributes $object): bool => $object->type() === 'file');
        ProgressBar::setFormatDefinition('custom', '[%bar%] %current%/%max% -- %message%');
        $progressBar = new ProgressBar($style, \count((array) $files));
        $progressBar->setFormat('custom');
        $progressBar->setMessage('');

        foreach ($files as $file) {
            $filesystem->setVisibility($file->path(), $visibility);

            $progressBar->advance();
            $progressBar->setMessage($file->path());
        }

        $progressBar->finish();
    }
}
