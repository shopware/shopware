<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Composer\IO\ConsoleIO;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

class PluginRefreshCommand extends Command
{
    /**
     * @var PluginService
     */
    private $pluginService;

    public function __construct(PluginService $pluginService)
    {
        parent::__construct();

        $this->pluginService = $pluginService;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('plugin:refresh')
            ->setDescription('Refreshes the plugins list in the storage from the file system')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $context = Context::createDefaultContext();
        $this->pluginService->refreshPlugins($context, new ConsoleIO($input, $output, new HelperSet()));

        $listInput = new StringInput('plugin:list');

        /** @var Application $application */
        $application = $this->getApplication();
        $application->doRun($listInput, $output);
    }
}
