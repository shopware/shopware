<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\PluginManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginUpdateCommand extends Command
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
            ->setName('plugin:update')
            ->setDescription('Updates a plugin.')
            ->addArgument('plugin', InputArgument::REQUIRED, 'Name of the plugin to be updated.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> updates a plugin.
EOF
            );
    }

    /**
     * {@inheritdoc}
     *
     * @throws PluginNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $pluginName = $input->getArgument('plugin');

        $plugin = $this->pluginManager->getPluginByName($pluginName, $context);

        $this->pluginManager->updatePlugin($plugin, $context);

        $io->success(sprintf('Plugin "%s" has been updated successfully.', $pluginName));
    }
}
