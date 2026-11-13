<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Shopware\Core\Framework\Console\OutputFormatTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @phpstan-import-type PluginInfo from KernelPluginLoader
 */
#[Package('framework')]
#[AsCommand(
    name: 'plugin:debug:mode',
    description: 'Deactivate all non shopware plugins',
)]
class PluginDebugModeCommand extends Command
{
    use OutputFormatTrait;

    /**
     * @internal
     *
     * @param EntityRepository<PluginCollection> $pluginRepo
     */
    public function __construct(
        private readonly EntityRepository $pluginRepo,
        private readonly ComposerPluginLoader $composerPluginLoader,
        private readonly PluginLifecycleService $pluginLifecycleService
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        /* $this->addFormatOption([self::FORMAT_TABLE, self::FORMAT_JSON]); */
        /** @deprecated tag:v6.8.0 - Use `--format json` instead */
        $this->addOption('uninstall', null, InputOption::VALUE_NONE, 'Uninstall all non showpare plugins');
        /* $this->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Filter the plugin list to a given term'); */
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createCLIContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));

        $deactivatedPluginCount = 0;
        $uninstalledPluginCount = 0;

        $plugins = $this->pluginRepo->search($criteria, $context)->getEntities();
        $shopwareNames = ["shopware ag", "shopware", "swag"];

        foreach ($plugins as $plugin) {
            $author = strtolower($plugin->getAuthor());
            if (!in_array($author, $shopwareNames)) {
                if ($input->getOption('uninstall')) {
                    ++$uninstalledPluginCount;
                    $this->pluginLifecycleService->uninstallPlugin($plugin, $context, true);
                } else {
                    ++$deactivatedPluginCount;
                    $this->pluginLifecycleService->deactivatePlugin($plugin, $context);
                }
            }
        }

        if ($deactivatedPluginCount !== 0) {
            $io->success(\sprintf('Deactivated %d plugins.', $deactivatedPluginCount));
            $io->note(\sprintf('You may want to clear the cache after %s plugin(s). To do so run the cache:clear command', ""));
        }

        if ($uninstalledPluginCount !== 0) {
            $io->success(\sprintf('Uninstalled %d plugins.', $uninstalledPluginCount));
            $io->note(\sprintf('You may want to clear the cache after %s plugin(s). To do so run the cache:clear command', ""));
        }
        if ($deactivatedPluginCount === 0 && $uninstalledPluginCount === 0) {
            $io->note(\sprintf('No plugins found which are not from shopware', ""));
        }

        return self::SUCCESS;
    }
}
