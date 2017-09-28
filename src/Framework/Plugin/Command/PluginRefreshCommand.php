<?php declare(strict_types=1);

namespace Shopware\Framework\Plugin\Command;

use Shopware\Framework\Plugin\PluginManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginRefreshCommand extends Command
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
            ->setName('plugin:refresh')
            ->setDescription('Refreshes plugin list.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $this->pluginManager->refreshPlugins();

        $io->comment('Refreshing available plugins from filesystem.');

        $listCommand = $this->getApplication()->find('plugin:list');
        $listCommand->run($input, $output);
    }
}
