<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Command;

use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Kernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AssetsPublishCommand extends Command
{
    protected static $defaultName = 'assets:publish';

    /**
     * @var AssetService
     */
    private $assetService;

    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(AssetService $assetService, Kernel $kernel)
    {
        parent::__construct();
        $this->assetService = $assetService;
        $this->kernel = $kernel;
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('Upload local assets to remote filesystem');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $kernelBundles = $this->kernel->getBundles();
        $output->writeln('Uploading assets of bundles');
        $progressBar = new ProgressBar($output, count($kernelBundles));
        foreach ($kernelBundles as $bundle) {
            $this->assetService->copyAssetsFromBundle($bundle->getName());
            $progressBar->advance();
        }
        $progressBar->finish();
        $output->writeln('');

        return 0;
    }
}
