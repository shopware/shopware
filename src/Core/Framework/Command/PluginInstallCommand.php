<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginInstallCommand extends Command
{
    use PluginCommandTrait;

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

    public function getPluginService(): PluginService
    {
        return $this->pluginService;
    }

    protected function configure(): void
    {
        $this
            ->setName('plugin:install')
            ->setDescription('Installs a plugin.')
            ->addArgument('plugins', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Name of the plugins to be installed.')
            ->addOption('activate', null, InputOption::VALUE_NONE, 'Activate plugin after installation.')
            ->addOption('reinstall', null, InputOption::VALUE_NONE, 'Reinstall the plugin')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> installs a plugin.
EOF
            );
    }

    /**
     * {@inheritdoc}
     *
     * @throws PluginNotFoundException
     * @throws PluginNotInstalledException
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $this->displayHeader($io);
        $context = Context::createDefaultContext();

        $plugins = $this->parsePluginArgument($input->getArgument('plugins'), $context);

        $io->text(sprintf('Installing %d plugins:', \count($plugins)));
        $io->listing($this->formatPluginList($plugins));

        /** @var PluginEntity $plugin */
        foreach ($plugins as $plugin) {
            if ($input->getOption('reinstall') && $plugin->getInstalledAt()) {
                $this->pluginLifecycleService->uninstallPlugin($plugin, $context);
            }

            if ($input->getOption('activate') && $plugin->getInstalledAt() && $plugin->getActive() === false) {
                $io->note(sprintf('Plugin "%s" is already installed. Activating.', $plugin->getName()));
                $this->pluginLifecycleService->activatePlugin($plugin, $context);

                continue;
            }

            if ($plugin->getInstalledAt()) {
                $io->note(sprintf('Plugin "%s" is already installed. Skipping.', $plugin->getLabel()));

                continue;
            }

            $activationSuffix = '';
            $message = 'Plugin "%s" has been installed%s successfully.';

            $this->pluginLifecycleService->installPlugin($plugin, $context);

            if ($input->getOption('activate')) {
                $this->pluginLifecycleService->activatePlugin($plugin, $context);
                $activationSuffix = ' and activated';
            }

            $io->text(sprintf($message, $plugin->getLabel(), $activationSuffix));
        }

        $io->success(sprintf('Installed %d plugins.', \count($plugins)));
    }
}
