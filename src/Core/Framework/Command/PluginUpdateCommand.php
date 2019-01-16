<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginUpdateCommand extends Command
{
    /**
     * @var PluginService
     */
    private $pluginService;

    /**
     * @var PluginLifecycleService
     */
    private $pluginLifecycleService;

    public function __construct(PluginService $pluginService, PluginLifecycleService $pluginLifecycleService)
    {
        parent::__construct();

        $this->pluginService = $pluginService;
        $this->pluginLifecycleService = $pluginLifecycleService;
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

        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        $this->pluginLifecycleService->updatePlugin($plugin, $context);

        $io->success(sprintf('Plugin "%s" has been updated successfully.', $pluginName));
    }
}
