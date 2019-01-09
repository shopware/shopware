<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginUpgradeCommand extends Command
{
    /**
     * @var PluginManager
     */
    private $pluginManager;

    public function __construct(PluginManager $pluginManager)
    {
        parent::__construct();

        $this->pluginManager = $pluginManager;
    }

    protected function configure(): void
    {
        $this
            ->setName('plugin:upgrade')
            ->setDescription('Upgrades a plugin.')
            ->addArgument('plugin', InputArgument::REQUIRED, 'Name of the plugin to be upgraded.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> updates a plugin.
EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $pluginName = $input->getArgument('plugin');

        $plugin = $this->pluginManager->getPluginByName($pluginName, $context);

        $this->pluginManager->upgradePlugin($plugin, $context);

        $io->success(sprintf('Plugin "%s" has been updated successfully.', $pluginName));
    }
}
