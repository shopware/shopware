<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class AssetInstallCommand extends Command
{
    protected static $defaultName = 'assets:install';

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var AssetService
     */
    private $assetService;

    public function __construct(KernelInterface $kernel, AssetService $assetService)
    {
        parent::__construct();
        $this->kernel = $kernel;
        $this->assetService = $assetService;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        foreach ($this->kernel->getBundles() as $bundle) {
            $io->writeln(sprintf('Copying files for bundle: %s', $bundle->getName()));
            $this->assetService->copyAssetsFromBundle($bundle->getName());
        }

        $io->success('Successfully copied all bundle files');

        return self::SUCCESS;
    }
}
