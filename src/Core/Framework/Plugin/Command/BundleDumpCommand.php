<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Plugin\BundleConfigDumper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BundleDumpCommand extends Command
{
    protected static $defaultName = 'bundle:dump';

    /**
     * @var BundleConfigDumper
     */
    private $bundleDumper;

    public function __construct(BundleConfigDumper $pluginDumper)
    {
        parent::__construct();

        $this->bundleDumper = $pluginDumper;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setAliases(['administration:dump:plugins', 'administration:dump:bundles'])
            ->setDescription('Creates a json file with the configuration for each active Shopware bundle.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bundleDumper->dump();

        $style = new ShopwareStyle($input, $output);
        $style->success('Dumped plugin configuration.');

        return self::SUCCESS;
    }
}
