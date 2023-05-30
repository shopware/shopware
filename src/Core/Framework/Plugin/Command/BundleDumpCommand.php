<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\BundleConfigGeneratorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'bundle:dump', description: 'Dumps the bundle configuration for a plugin', aliases: ['administration:dump:plugins', 'administration:dump:bundles'])]
#[Package('core')]
class BundleDumpCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BundleConfigGeneratorInterface $bundleDumper,
        private readonly string $projectDir
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addArgument('dumpFilePath', InputArgument::OPTIONAL, 'By default to var/plugins.json', 'var/plugins.json');
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
