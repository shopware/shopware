<?php declare(strict_types=1);

namespace Shopware\Framework\Plugin\Command;

use Shopware\Framework\Plugin\PluginManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PluginUpdateCommand extends Command
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
            ->setName('plugin:update')
            ->setDescription('Updates the plugin list.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->pluginManager->updatePlugins();

        $listCommand = $this->getApplication()->find('plugin:list');
        $listCommand->run($input, $output);
    }
}
