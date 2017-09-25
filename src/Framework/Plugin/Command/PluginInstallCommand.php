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

class PluginInstallCommand extends Command
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
            ->setName('plugin:install')
            ->setDescription('Installs a plugin.')
            ->addArgument('plugin', InputArgument::REQUIRED, 'Name of the plugin to be installed.')
            ->addOption('activate', null, InputOption::VALUE_NONE, 'Activate plugin after installation.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> installs a plugin.
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

        if ($plugin->getInstallationDate()) {
            $io->writeln(sprintf('Plugin "%s" is already installed.', $pluginName));

            return 1;
        }

        $activationSuffix = '';
        $message = 'Plugin "%s" has been installed%s successfully.';

        $this->pluginManager->installPlugin($plugin);

        if ($input->getOption('activate')) {
            $this->pluginManager->activatePlugin($plugin);
            $activationSuffix = ' and activated';
        }

        $io->success(sprintf($message, $pluginName, $activationSuffix));
    }
}
