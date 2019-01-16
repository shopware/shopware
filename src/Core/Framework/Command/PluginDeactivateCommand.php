<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\PluginNotActivatedException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginDeactivateCommand extends Command
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
     *
     * @throws PluginNotFoundException
     * @throws PluginNotInstalledException
     * @throws PluginNotActivatedException
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

            $this->pluginLifecycleService->deactivatePlugin($plugin, $context);

            $io->text(sprintf('Plugin "%s" has been deactivated successfully.', $plugin->getLabel()));
        }

        $io->success(sprintf('Deactivated %d plugins.', \count($plugins)));
    }
}
