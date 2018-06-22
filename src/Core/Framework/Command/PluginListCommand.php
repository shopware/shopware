<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\Plugin\PluginManager;
use Shopware\Core\Framework\Plugin\PluginStruct;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginListCommand extends Command
{
    /**
     * @var PluginManager
     */
    private $pluginManager;

    public function __construct(PluginManager $pluginManager)
    {
        parent::__construct();

        $this->pluginManager = $pluginManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('plugin:list')
            ->setDescription('Show a list of available plugins.')
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Filter to a given text', null)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Plugin Manager');

        $plugins = $this->pluginManager->getPlugins();

        if ($filter = $input->getOption('filter')) {
            $io->comment(sprintf('Filtering for: %s', $filter));

            $plugins = array_filter($plugins, function (PluginStruct $plugin) use ($filter) {
                return stripos($plugin->getName(), $filter) !== false || stripos($plugin->getLabel(), $filter) !== false;
            });
        }

        $pluginTable = [];

        $active = $installed = 0;
        $available = count($plugins);

        foreach ($plugins as $plugin) {
            $pluginTable[] = [
                $plugin->getName(),
                $plugin->getLabel(),
                $plugin->getVersion(),
                $plugin->getAuthor(),
                $plugin->getActive() ? 'Yes' : 'No',
                $plugin->getInstallationDate() ? 'Yes' : 'No',
            ];

            if ($plugin->getActive()) {
                ++$active;
            }

            if ($plugin->getInstallationDate()) {
                ++$installed;
            }
        }

        $io->table(['Plugin', 'Label', 'Version', 'Author', 'Active', 'Installed'], $pluginTable);
        $io->text(sprintf('%d plugins, %d installed, %d active', $available, $installed, $active));
    }
}
