<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\PluginManager;
use Shopware\Core\Framework\Struct\Plugin;
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

        /** @var Plugin $plugin */
        foreach ($plugins as $plugin) {
            if ($input->getOption('reinstall') && $plugin->getInstallationDate()) {
                $this->pluginManager->uninstallPlugin($plugin);
            }

            if ($input->getOption('activate') && $plugin->getInstallationDate() && $plugin->isActive() === false) {
                $io->note(sprintf('Plugin "%s" is already installed. Activating.', $plugin->getName()));
                $this->pluginManager->activatePlugin($plugin);

                continue;
            }

            if ($plugin->getInstallationDate()) {
                $io->note(sprintf('Plugin "%s" is already installed. Skipping.', $plugin->getLabel()));

                continue;
            }

            $activationSuffix = '';
            $message = 'Plugin "%s" has been installed%s successfully.';

            $this->pluginManager->installPlugin($plugin);

            if ($input->getOption('activate')) {
                $this->pluginManager->activatePlugin($plugin);
                $activationSuffix = ' and activated';
            }

            $io->text(sprintf($message, $plugin->getLabel(), $activationSuffix));
        }

        $io->success(sprintf('Installed %d plugins.', count($plugins)));
    }
}
