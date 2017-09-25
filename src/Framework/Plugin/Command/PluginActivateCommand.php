<?php

namespace Shopware\Framework\Plugin\Command;

use Shopware\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Framework\Plugin\PluginManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginActivateCommand extends Command
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


    protected function configure()
    {
        $this
            ->setName('plugin:activate')
            ->setDescription('Activates a plugin.')
            ->addArgument('plugin', InputArgument::REQUIRED, 'Name of the plugin to be installed.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> activates a plugin.
EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $pluginName = $input->getArgument('plugin');

        try {
            $plugin = $this->pluginManager->getPluginByName($pluginName);
        } catch (PluginNotFoundException $e) {
            $io->error($e->getMessage());

            return 1;
        }

        if (null === $plugin->getInstallationDate()) {
            $io->writeln(sprintf('Plugin "%s" must be installed.', $pluginName));

            return 1;
        }

        if ($plugin->isActive()) {
            $io->writeln(sprintf('Plugin "%s" is already active.', $pluginName));

            return 1;
        }

        $this->pluginManager->activatePlugin($plugin);

        $io->success(sprintf('Plugin "%s" has been activated successfully.', $pluginName));
    }
}
