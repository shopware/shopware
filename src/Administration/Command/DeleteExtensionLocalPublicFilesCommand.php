<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'administration:delete-extension-local-public-files',
    description: 'Deletes all files in the local public folder of the extension. This command should run after assets:install so the assets are available in the public folder.',
)]
#[Package('admin')]
class DeleteExtensionLocalPublicFilesCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly KernelInterface $kernel)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fs = new Filesystem();
        $io = new SymfonyStyle($input, $output);

        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            $bundlePath = $bundle->getPath();
            $publicPath = $bundlePath . '/Resources/public';

            if (!is_dir($publicPath)) {
                continue;
            }

            if (file_exists($bundlePath . '/Resources/public/administration/css')) {
                touch($bundle->getPath() . '/Resources/.administration-css');
            }

            if (file_exists($bundlePath . '/Resources/public/administration/js')) {
                touch($bundle->getPath() . '/Resources/.administration-js');
            }

            $fs->remove($publicPath);

            $io->success(sprintf('Removed public assets for bundle "%s"', $bundle->getName()));
        }

        return self::SUCCESS;
    }
}
