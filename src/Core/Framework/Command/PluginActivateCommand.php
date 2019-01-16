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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginActivateCommand extends Command
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
            ->setName('plugin:activate')
            ->setDescription('Activates a plugin.')
            ->addArgument('plugins', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Name of the plugins to be activated.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> activates a plugin.
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

        $io->text(sprintf('Activating %d plugins:', \count($plugins)));
        $io->listing($this->formatPluginList($plugins));

        /** @var PluginEntity $plugin */
        foreach ($plugins as $plugin) {
            if ($plugin->getInstalledAt() === null) {
                $io->note(sprintf('Plugin "%s" must be installed. Skipping.', $plugin->getLabel()));

                continue;
            }

            if ($plugin->getActive()) {
                $io->note(sprintf('Plugin "%s" is already active. Skipping.', $plugin->getLabel()));

                continue;
            }

            $this->pluginLifecycleService->activatePlugin($plugin, $context);

            $io->text(sprintf('Plugin "%s" has been activated successfully.', $plugin->getLabel()));
        }

        $io->success(sprintf('Activated %d plugins.', \count($plugins)));
    }
}
