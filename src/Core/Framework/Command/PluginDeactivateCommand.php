<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginManager;
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

    protected function configure(): void
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
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $this->displayHeader($io);
        $context = Context::createDefaultContext();

        $plugins = $this->parsePluginArgument($input->getArgument('plugins'), $context);

        $io->text(sprintf('Deactivating %d plugins:', \count($plugins)));
        $io->listing($this->formatPluginList($plugins));

        /** @var PluginEntity $plugin */
        foreach ($plugins as $plugin) {
            if ($plugin->getInstalledAt() === null) {
                $io->note(sprintf('Plugin "%s" must be installed. Skipping.', $plugin->getLabel()));

                continue;
            }

            if ($plugin->getActive() === false) {
                $io->note(sprintf('Plugin "%s" must be activated. Skipping.', $plugin->getLabel()));

                continue;
            }

            $this->pluginManager->deactivatePlugin($plugin, $context);

            $io->text(sprintf('Plugin "%s" has been deactivated successfully.', $plugin->getLabel()));
        }

        $io->success(sprintf('Deactivated %d plugins.', \count($plugins)));
    }
}
