<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'plugin:list',
    description: 'Lists all plugins',
)]
#[Package('core')]
class PluginListCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $pluginRepo)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Return result as json of plugin entities')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Filter the plugin list to a given term');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $context = Context::createDefaultContext();

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
        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepo->search($criteria, $context)->getEntities();

        if ($input->getOption('json')) {
            $output->write(json_encode($plugins, \JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $pluginTable = [];
        $active = $installed = $upgradeable = 0;

        $io->title('Shopware Plugin Service');

        if ($filter) {
            $io->comment(sprintf('Filtering for: %s', $filter));
        }

        foreach ($plugins as $plugin) {
            $pluginActive = $plugin->getActive();
            $pluginInstalled = $plugin->getInstalledAt();
            $pluginUpgradeable = $plugin->getUpgradeVersion();

            $pluginTable[] = [
                $plugin->getName(),
                $plugin->getLabel(),
                $plugin->getVersion(),
                $pluginUpgradeable,
                $plugin->getAuthor(),
                $pluginInstalled ? 'Yes' : 'No',
                $pluginActive ? 'Yes' : 'No',
                $pluginUpgradeable ? 'Yes' : 'No',
            ];

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

        $io->table(
            ['Plugin', 'Label', 'Version', 'Upgrade version', 'Author', 'Installed', 'Active', 'Upgradeable'],
            $pluginTable
        );
        $io->text(
            sprintf(
                '%d plugins, %d installed, %d active , %d upgradeable',
                \count($plugins),
                $installed,
                $active,
                $upgradeable
            )
        );

        return self::SUCCESS;
    }
}
