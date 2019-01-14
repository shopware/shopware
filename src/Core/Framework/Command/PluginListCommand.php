<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginListCommand extends Command
{
    /**
     * @var RepositoryInterface
     */
    private $pluginRepo;

    public function __construct(RepositoryInterface $pluginRepo)
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
            ->setName('plugin:list')
            ->setDescription('Show a list of available plugins.')
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Filter to a given text')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Plugin Manager');
        $context = Context::createDefaultContext();

        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepo->search(new Criteria(), $context)->getEntities();

        $filter = $input->getOption('filter');
        if ($filter) {
            $io->comment(sprintf('Filtering for: %s', $filter));

            $plugins = $plugins->filter(function (PluginEntity $plugin) use ($filter) {
                return stripos($plugin->getName(), $filter) !== false
                    || stripos($plugin->getLabel(), $filter) !== false;
            });
        }

        $pluginTable = [];
        $active = $installed = $upgradeable = 0;

        /** @var PluginEntity $plugin */
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
                $pluginActive ? 'Yes' : 'No',
                $pluginInstalled ? 'Yes' : 'No',
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
            ['Plugin', 'Label', 'Version', 'Upgrade version', 'Author', 'Active', 'Installed', 'Upgradeable'],
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
    }
}
