<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Command;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package core
 */
#[AsCommand(
    name: 's3:set-visibility',
    description: 'Sets the visibility of all files in the s3 filesystem to public',
)]
class S3FilesystemVisibilityCommand extends Command
{
    /**
     * @var FilesystemOperator
     */
    private $filesystemPrivate;

    /**
     * @var FilesystemOperator
     */
    private $filesystemPublic;

    /**
     * @var FilesystemOperator
     */
    private $filesystemTheme;

    /**
     * @var FilesystemOperator
     */
    private $filesystemSitemap;

    /**
     * @var FilesystemOperator
     */
    private $filesystemAsset;

    /**
     * @internal
     */
    public function __construct(
        FilesystemOperator $filesystemPrivate,
        FilesystemOperator $filesystemPublic,
        FilesystemOperator $filesystemTheme,
        FilesystemOperator $filesystemSitemap,
        FilesystemOperator $filesystemAsset
    ) {
        parent::__construct();
        $this->filesystemPrivate = $filesystemPrivate;
        $this->filesystemPublic = $filesystemPublic;
        $this->filesystemTheme = $filesystemTheme;
        $this->filesystemSitemap = $filesystemSitemap;
        $this->filesystemAsset = $filesystemAsset;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Sets visibility for all objects in corresponding bucket of S3 storage.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new ShopwareStyle($input, $output);

        $style->warning('If both private and public objects are stored in the same bucket, this command will set all of them public.');
        $continue = $style->confirm('Continue?');

        if (!$continue) {
            return 0;
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

        return 0;
    }

    private function setVisibility(FilesystemOperator $filesystem, ShopwareStyle $style, string $visibility): void
    {
        $files = array_filter($filesystem->listContents('/', true)->toArray(), function (StorageAttributes $object): bool {
            return $object->type() === 'file';
        });
        ProgressBar::setFormatDefinition('custom', '[%bar%] %current%/%max% -- %message%');
        $progressBar = new ProgressBar($style, \count($files));
        $progressBar->setFormat('custom');
        $progressBar->setMessage('');

        foreach ($files as $file) {
            $filesystem->setVisibility($file['path'], $visibility);

            $progressBar->advance();
            $progressBar->setMessage($file['path']);
        }

        $progressBar->finish();
    }
}
