<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
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

    protected function configure()
    {
        $this
            ->setName('plugin:upgrade')
            ->setDescription('Update a plugin.')
            ->addArgument('plugin', InputArgument::REQUIRED, 'Name of the plugin to be updated.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> updates a plugin.
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

        $tenantId = $input->getOption('tenant-id');
        $this->pluginManager->updatePlugin($plugin, $tenantId);

        $io->success(sprintf('Plugin "%s" has been updated successfully.', $pluginName));
    }
}
