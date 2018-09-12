<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\PluginManager;
use Shopware\Core\Framework\Plugin\PluginStruct;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginUninstallCommand extends Command
{
    use PluginCommandTrait;

    /**
     * @var PluginManager
     */
    private $pluginManager;

    public function __construct(PluginManager $pluginManager)
    {
        parent::__construct();

        $this->pluginManager = $pluginManager;
    }

    public function getPluginManager(): PluginManager
    {
        return $this->pluginManager;
    }

    protected function configure()
    {
        $this
            ->setName('plugin:uninstall')
            ->setDescription('Uninstalls a plugin.')
            ->addArgument('plugins', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Name of the plugins to be uninstalled.')
            ->addOption('remove-userdata', null, InputOption::VALUE_NONE, 'The plugin removes all created data')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> uninstalls a plugin.
EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $this->displayHeader($io);

        try {
            $plugins = $this->parsePluginArgument($input->getArgument('plugins'));
        } catch (PluginNotFoundException $e) {
            $io->error($e->getMessage());

            return 1;
        }

        $io->text(sprintf('Installing %d plugins:', count($plugins)));
        $io->listing($this->formatPluginList($plugins));

        $tenantId = $input->getOption('tenant-id');
        $removeUserdata = (bool) $input->getOption('remove-userdata');

        /** @var PluginStruct $plugin */
        foreach ($plugins as $plugin) {
            if ($plugin->getInstallationDate() === null) {
                $io->note(sprintf('Plugin "%s" is not installed. Skipping.', $plugin->getLabel()));

                continue;
            }

            $this->pluginManager->uninstallPlugin($plugin, $tenantId, $removeUserdata);

            $io->text(sprintf('Plugin "%s" has been uninstalled successfully.', $plugin->getLabel()));
        }

        $io->success(sprintf('Uninstalled %d plugins.', count($plugins)));
    }
}
