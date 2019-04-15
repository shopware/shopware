<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractPluginLifecycleCommand extends Command
{
    /**
     * @var PluginLifecycleService
     */
    protected $pluginLifecycleService;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    public function __construct(
        PluginLifecycleService $pluginLifecycleService,
        EntityRepositoryInterface $pluginRepo
    ) {
        parent::__construct();

        $this->pluginLifecycleService = $pluginLifecycleService;
        $this->pluginRepo = $pluginRepo;
    }

    protected function configureCommand(string $lifecycleMethod): void
    {
        $this
            ->setName(sprintf('plugin:%s', $lifecycleMethod))
            ->setDescription(sprintf('%ss given plugins', ucfirst($lifecycleMethod)))
            ->addArgument(
                'plugins',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'List of plugins'
            )
            ->addOption(
                'refresh',
                'r',
                InputOption::VALUE_NONE,
                'Use this option to refresh the plugins before executing the command'
            );
    }

    protected function prepareExecution(
        string $lifecycleMethod,
        SymfonyStyle $io,
        InputInterface $input,
        Context $context
    ): PluginCollection {
        $io->title('Shopware Plugin Lifecycle Service');

        $plugins = $this->parsePluginArgument($input->getArgument('plugins'), $context);

        if ($plugins->count() === 0) {
            $io->warning('No plugins found');
            $io->text('Try the plugin:refresh command first, or change your search term');

            return $plugins;
        }

        if ($input->getOption('refresh')) {
            $io->note('Refreshing plugin list');
            $this->refreshPlugins();
        }

        $io->text(sprintf('%s %d plugin(s):', ucfirst($lifecycleMethod), \count($plugins)));
        $io->listing($this->formatPluginList($plugins));

        return $plugins;
    }

    protected function refreshPlugins(): void
    {
        $input = new StringInput('plugin:refresh -s');
        /** @var Application $application */
        $application = $this->getApplication();
        $application->doRun($input, new NullOutput());
    }

    private function parsePluginArgument(array $arguments, Context $context): PluginCollection
    {
        $plugins = array_unique($arguments);
        $filter = [];
        foreach ($plugins as $plugin) {
            $filter[] = new ContainsFilter('name', $plugin);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $filter));

        /** @var PluginCollection $pluginCollection */
        $pluginCollection = $this->pluginRepo->search($criteria, $context)->getEntities();

        return $pluginCollection;
    }

    /**
     * @return string[]
     */
    private function formatPluginList(PluginCollection $plugins): array
    {
        $pluginList = [];
        foreach ($plugins as $plugin) {
            $pluginList[] = sprintf('%s (v%s)', $plugin->getLabel(), $plugin->getVersion());
        }

        return $pluginList;
    }
}
