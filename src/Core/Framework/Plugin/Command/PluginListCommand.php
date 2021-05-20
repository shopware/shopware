<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PluginListCommand extends Command
{
    protected static $defaultName = 'plugin:list';

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    public function __construct(EntityRepositoryInterface $pluginRepo)
    {
        parent::__construct();
        $this->pluginRepo = $pluginRepo;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Show a list of available plugins.')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Filter the plugin list to a given term');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $io->title('Shopware Plugin Service');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $filter = $input->getOption('filter');
        if ($filter) {
            $io->comment(sprintf('Filtering for: %s', $filter));

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

        $pluginTable = [];
        $active = $installed = $upgradeable = 0;

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
