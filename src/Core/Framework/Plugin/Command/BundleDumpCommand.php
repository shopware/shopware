<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Plugin\BundleConfigGeneratorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BundleDumpCommand extends Command
{
    protected static $defaultName = 'bundle:dump';

    private BundleConfigGeneratorInterface $bundleDumper;

    private string $projectDir;

    public function __construct(BundleConfigGeneratorInterface $pluginDumper, string $projectDir)
    {
        parent::__construct();

        $this->bundleDumper = $pluginDumper;
        $this->projectDir = $projectDir;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setAliases(['administration:dump:plugins', 'administration:dump:bundles'])
            ->setDescription('Creates a json file with the configuration for each active Shopware bundle.')
            ->addArgument('dumpFilePath', InputArgument::OPTIONAL, 'By default to var/plugins.json', 'var/plugins.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->bundleDumper->getConfig();

        \file_put_contents(
            $this->projectDir . '/' . $input->getArgument('dumpFilePath'),
            \json_encode($config, \JSON_PRETTY_PRINT)
        );

        $style = new ShopwareStyle($input, $output);
        $style->success('Dumped plugin configuration.');

        return self::SUCCESS;
    }
}
