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
     * @var int
     */
    private $installed;

    /**
     * @var int
     */
    private $upgradeable;

    /**
     * @var int
     */
    private $active;

    /**
     * @var int
     */
    private $total;

    /**
     * @var string[]
     */
    private $availableFormats;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    public function __construct(EntityRepositoryInterface $pluginRepo)
    {
        parent::__construct();
        $this->availableFormats = ['table', 'json'];
        $this->total = 0;
        $this->active = 0;
        $this->installed = 0;
        $this->upgradeable = 0;
        $this->pluginRepo = $pluginRepo;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Show a list of available plugins.')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Filter the plugin list to a given term')
            ->addOption(
                'format',
                null,
                InputOption::VALUE_REQUIRED,
                'Choose "table" or "json" format to output to',
                'table'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = strtolower($input->getOption('format'));
        if (!in_array($format, $this->availableFormats, true)) {
            throw new \Exception(sprintf(
                'Format %s is not available for this command (available formats: %s)',
                $format,
                implode(', ', $this->availableFormats)
            ));
        }
        $io = new ShopwareStyle($input, $output);
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
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
        $pluginTable = [];

        foreach ($plugins as $plugin) {
            $pluginActive = $plugin->getActive();
            $pluginInstalled = $plugin->getInstalledAt();
            $pluginUpgradeable = $plugin->getUpgradeVersion();

            $pluginData = [
                'name' => $plugin->getName(),
                'label' => $plugin->getLabel(),
                'version' => $plugin->getVersion(),
                'upgrade_version' => $pluginUpgradeable,
                'author' => $plugin->getAuthor(),
                'installed' => $pluginInstalled ? 'Yes' : 'No',
                'active' => $pluginActive ? 'Yes' : 'No',
                'upgradeable' => $pluginUpgradeable ? 'Yes' : 'No',
            ];

            if ($format === 'json') {
                $pluginData['path'] = $plugin->getPath();
            }

            $pluginTable[] = $pluginData;

            ++$this->total;

            if ($pluginActive) {
                ++$this->active;
            }

            if ($pluginInstalled) {
                ++$this->installed;
            }

            if ($pluginUpgradeable) {
                ++$this->upgradeable;
            }
        }

        if ($format === 'json') {
            $this->outputJson($io, $pluginTable);
        } elseif ($format === 'table') {
            $this->outputTable($io, $pluginTable, $filter);
        }

        return 0;
    }

    protected function outputTable(
        ShopwareStyle $io,
        array $pluginTable,
        ?string $filter
    ): void {
        $io->title('Shopware Plugin Service');
        if ($filter) {
            $io->comment(sprintf('Filtering for: %s', $filter));
        }

        $io->table(
            ['Plugin', 'Label', 'Version', 'Upgrade version', 'Author', 'Installed', 'Active', 'Upgradeable'],
            $pluginTable
        );
        $io->text(
            sprintf(
                '%d plugins, %d installed, %d active , %d upgradeable',
                $this->total,
                $this->installed,
                $this->active,
                $this->upgradeable
            )
        );
    }

    protected function outputJson(ShopwareStyle $io, array $pluginTable): void
    {
        $stats = [
            'total' => $this->total,
            'installed' => $this->installed,
            'active' => $this->active,
            'upgradeable' => $this->upgradeable,
        ];
        $io->write(json_encode(['plugins' => $pluginTable, 'stats' => $stats]));
    }
}
