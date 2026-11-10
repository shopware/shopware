<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Composer\IO\ConsoleIO;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginComposerDependencyService;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[Package('framework')]
#[AsCommand(name: 'plugin:update:all', description: 'Install all available plugin updates')]
class PluginUpdateAllCommand extends Command
{
    /**
     * @internal
     *
     * @param EntityRepository<PluginCollection> $pluginRepository
     */
    public function __construct(
        private readonly PluginService $pluginService,
        private readonly EntityRepository $pluginRepository,
        private readonly PluginLifecycleService $pluginLifecycleService,
        private readonly ?PluginComposerDependencyService $composerDependencyService = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'skip-asset-build',
            null,
            InputOption::VALUE_NONE,
            'Use this option to skip asset building'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composerInput = clone $input;
        $composerInput->setInteractive(false);
        $helperSet = $this->getHelperSet();
        \assert($helperSet instanceof HelperSet);

        $context = Context::createCLIContext();
        if ($this->composerDependencyService !== null) {
            $context->addState(PluginLifecycleService::STATE_SEPARATE_COMPOSER_STEP);
        }

        if ($input->getOption('skip-asset-build')) {
            $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
        }

        $this->pluginService->refreshPlugins($context, new ConsoleIO($composerInput, $output, $helperSet));

        $plugins = $this->pluginRepository->search(new Criteria(), $context)->getEntities();

        foreach ($plugins as $plugin) {
            if ($plugin->getUpgradeVersion() === null || $plugin->getActive() === false) {
                continue;
            }

            $currentVersion = $plugin->getVersion();
            $this->composerDependencyService?->requireDependency($plugin, $context);
            $this->pluginLifecycleService->updatePlugin($plugin, $context);
            $this->composerDependencyService?->removeDependency($plugin, $context);
            $output->writeln(\sprintf('Updated plugin %s from version %s to version %s', $plugin->getName(), $currentVersion, $plugin->getVersion()));
        }

        return self::SUCCESS;
    }
}
