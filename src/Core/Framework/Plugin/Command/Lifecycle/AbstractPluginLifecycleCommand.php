<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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

    /**
     * @return string[]
     */
    public function formatPluginList(PluginCollection $plugins): array
    {
        $pluginList = [];
        foreach ($plugins as $plugin) {
            $pluginList[] = sprintf('%s (v%s)', $plugin->getLabel(), $plugin->getVersion());
        }

        return $pluginList;
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
            );
    }

    protected function prepareExecution(
        string $lifecycleMethod,
        SymfonyStyle $io,
        array $pluginsArgument,
        Context $context
    ): PluginCollection {
        $io->title('Shopware Plugin Lifecycle Service');
        $io->note('Refreshing plugin list');
        $this->refreshPlugins();

        $plugins = $this->parsePluginArgument($pluginsArgument, $context);

        $io->text(sprintf('%s %d plugin(s):', ucfirst($lifecycleMethod), \count($plugins)));
        $io->listing($this->formatPluginList($plugins));

        return $plugins;
    }

    protected function refreshPlugins(): void
    {
        $listInput = new StringInput('plugin:refresh -s');
        /** @var Application $application */
        $application = $this->getApplication();
        $application->doRun($listInput, new NullOutput());
    }

    private function parsePluginArgument(array $arguments, Context $context): PluginCollection
    {
        $plugins = array_unique($arguments);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', $plugins));
        /** @var PluginCollection $pluginCollection */
        $pluginCollection = $this->pluginRepo->search($criteria, $context)->getEntities();

        return $pluginCollection;
    }
}
