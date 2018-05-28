<?php declare(strict_types=1);

namespace Shopware\Framework\Command;

use Shopware\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Framework\Plugin\PluginManager;
use Shopware\Framework\Plugin\Struct\Plugin;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginDeactivateCommand extends Command
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
            ->setName('plugin:deactivate')
            ->setDescription('Deactivates a plugin.')
            ->addArgument('plugins', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Name of the plugins to be activated.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> deactivates a plugin.
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

        $io->text(sprintf('Deactivating %d plugins:', count($plugins)));
        $io->listing($this->formatPluginList($plugins));

        /** @var Plugin $plugin */
        foreach ($plugins as $plugin) {
            if ($plugin->getInstallationDate() === null) {
                $io->note(sprintf('Plugin "%s" must be installed. Skipping.', $plugin->getLabel()));

                continue;
            }

            if ($plugin->isActive() === false) {
                $io->note(sprintf('Plugin "%s" must be activated. Skipping.', $plugin->getLabel()));

                continue;
            }

            $this->pluginManager->deactivatePlugin($plugin);

            $io->text(sprintf('Plugin "%s" has been deactivated successfully.', $plugin->getLabel()));
        }

        $io->success(sprintf('Deactivated %d plugins.', count($plugins)));
    }
}
