<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Command;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class S3FilesystemVisibilityCommand extends Command
{
    protected static $defaultName = 's3:set-visibility';

    /**
     * @var FilesystemInterface
     */
    private $filesystemPrivate;

    /**
     * @var FilesystemInterface
     */
    private $filesystemPublic;

    /**
     * @var FilesystemInterface
     */
    private $filesystemTheme;

    /**
     * @var FilesystemInterface
     */
    private $filesystemSitemap;

    /**
     * @var FilesystemInterface
     */
    private $filesystemAsset;

    public function __construct(
        FilesystemInterface $filesystemPrivate,
        FilesystemInterface $filesystemPublic,
        FilesystemInterface $filesystemTheme,
        FilesystemInterface $filesystemSitemap,
        FilesystemInterface $filesystemAsset
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

    private function setVisibility(FilesystemInterface $filesystem, ShopwareStyle $style, string $visibility): void
    {
        $files = array_filter($filesystem->listContents('/', true), function (array $object): bool {
            return $object['type'] === 'file';
        });
        ProgressBar::setFormatDefinition('custom', '[%bar%] %current%/%max% -- %message%');
        $progressBar = new ProgressBar($style, \count($files));
        $progressBar->setFormat('custom');

        foreach ($files as $file) {
            if ($file['type'] === 'file') {
                $filesystem->setVisibility($file['path'], $visibility);

                $progressBar->advance();
                $progressBar->setMessage($file['path']);
            }
        }

        $progressBar->finish();
    }
}
