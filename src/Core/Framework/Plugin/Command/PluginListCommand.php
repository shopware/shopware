<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Console\OutputFormatTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @phpstan-import-type PluginInfo from KernelPluginLoader
 */
#[AsCommand(
    name: 'plugin:list',
    description: 'Lists all plugins',
)]
#[Package('framework')]
class PluginListCommand extends Command
{
    use OutputFormatTrait;

    /**
     * @internal
     *
     * @param EntityRepository<PluginCollection> $pluginRepo
     */
    public function __construct(
        private readonly EntityRepository $pluginRepo,
        private readonly ComposerPluginLoader $composerPluginLoader
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addFormatOption([self::FORMAT_TABLE, self::FORMAT_JSON]);
        /** @deprecated tag:v6.8.0 - Use `--format json` instead */
        $this->addOption('json', null, InputOption::VALUE_NONE, '[DEPRECATED] Use `--format json` instead.');
        $this->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Filter the plugin list to a given term');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $context = Context::createCLIContext();

        if ($input->getOption('json')) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'The "--json" option of the "plugin:list" command is deprecated and will be removed in v6.8.0. Use "--format json" instead.'
            );
            $input->setOption('format', self::FORMAT_JSON);
        }

        $format = $this->resolveFormat($input, $output, [self::FORMAT_TABLE, self::FORMAT_JSON]);
        if ($format === null) {
            return self::INVALID;
        }

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $filter = $input->getOption('filter');
        if ($filter) {
            $criteria->addFilter(new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [
                    new ContainsFilter('name', $filter),
                    new ContainsFilter('label', $filter),
                ]
            ));
        }

        $plugins = $this->pluginRepo->search($criteria, $context)->getEntities();

        if ($format === self::FORMAT_JSON) {
            $output->write(json_encode($plugins, \JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $composerInstalled = $this->getComposerPluginLoaderPackages();

        $pluginTable = [];
        $active = $installed = $upgradeable = 0;

        $io->title('Shopware Plugin Service');

        if ($filter) {
            $io->comment(\sprintf('Filtering for: %s', $filter));
        }

        $composerInstalledAndRegistered = [];

        foreach ($plugins as $plugin) {
            $pluginActive = $plugin->getActive();
            $pluginInstalled = $plugin->getInstalledAt();
            $pluginUpgradeable = $plugin->getUpgradeVersion();
            $pluginComposerName = $plugin->getComposerName() ?? '';
            $isComposerInstalled = $pluginComposerName !== '' && ($composerInstalled[$pluginComposerName] ?? false);

            $pluginTable[] = [
                $plugin->getName(),
                mb_strimwidth($plugin->getLabel(), 0, 40, '...'),
                $plugin->getComposerName() ?? '',
                $plugin->getVersion(),
                $pluginUpgradeable,
                $plugin->getAuthor(),
                $pluginInstalled ? 'Yes' : 'No',
                $pluginActive ? 'Yes' : 'No',
                $pluginUpgradeable ? 'Yes' : 'No',
                $isComposerInstalled ? 'Yes' : 'No',
            ];

            if ($isComposerInstalled) {
                $composerInstalledAndRegistered[$pluginComposerName] = true;
            }

            if ($pluginActive) {
                ++$active;
            }

            if ($pluginInstalled) {
                ++$installed;
            }

            if ($pluginUpgradeable) {
                ++$upgradeable;
            }
        }

        foreach ($composerInstalled as $composerName => $plugin) {
            if (isset($composerInstalledAndRegistered[$composerName])) {
                continue;
            }

            $pluginTable[] = [
                $plugin['name'],
                '',
                '',
                $plugin['version'],
                '',
                '',
                'No',
                'No',
                '',
                'Yes',
            ];
        }

        $io->table(
            ['Plugin', 'Label', 'Composer name', 'Version', 'Upgrade version', 'Author', 'Installed', 'Active', 'Upgradeable', 'Required by composer'],
            $pluginTable
        );
        $io->text(
            \sprintf(
                '%d plugins, %d installed, %d active , %d upgradeable',
                \count($pluginTable),
                $installed,
                $active,
                $upgradeable
            )
        );

        return self::SUCCESS;
    }

    /**
     * @return array<string, PluginInfo>
     */
    private function getComposerPluginLoaderPackages(): array
    {
        $plugins = $this->composerPluginLoader->fetchPluginInfos();
        $packages = [];
        foreach ($plugins as $plugin) {
            $packages[$plugin['composerName']] = $plugin;
        }

        return $packages;
    }
}
