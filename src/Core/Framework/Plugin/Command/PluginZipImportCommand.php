<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Composer\IO\ConsoleIO;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\NoPluginFoundInZipException;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PluginZipImportCommand extends Command
{
    protected static $defaultName = 'plugin:zip-import';

    /**
     * @var PluginManagementService
     */
    private $pluginManagementService;

    /**
     * @var PluginService
     */
    private $pluginService;

    public function __construct(PluginManagementService $pluginManagementService, PluginService $pluginService)
    {
        parent::__construct();
        $this->pluginManagementService = $pluginManagementService;
        $this->pluginService = $pluginService;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setDescription('Import plugin zip file.')
            ->addArgument('zip-file', InputArgument::REQUIRED, 'Zip file that contains a shopware platform plugin.')
            ->addOption('no-refresh', null, InputOption::VALUE_OPTIONAL, 'Do not refresh plugin list.')
            ->addOption('delete', null, InputOption::VALUE_OPTIONAL, 'Delete the zip file after importing successfully.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $zipFile = $input->getArgument('zip-file');
        $io = new ShopwareStyle($input, $output);
        $io->title('Shopware Plugin Zip Import');

        try {
            $this->pluginManagementService->extractPluginZip($zipFile, (bool) $input->getOption('delete'));
        } catch (NoPluginFoundInZipException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        $io->success('Successfully import zip file ' . basename($zipFile));

        if (!$input->getOption('no-refresh')) {
            $this->pluginService->refreshPlugins(
                Context::createDefaultContext(),
                new ConsoleIO($input, $output, new HelperSet())
            );
            $io->success('Plugin list refreshed');
        }

        return self::SUCCESS;
    }
}
