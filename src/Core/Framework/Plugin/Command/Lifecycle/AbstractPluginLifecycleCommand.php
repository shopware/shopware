<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[Package('core')]
abstract class AbstractPluginLifecycleCommand extends Command
{
    public function __construct(
        protected PluginLifecycleService $pluginLifecycleService,
        private readonly EntityRepository $pluginRepo,
        protected CacheClearer $cacheClearer
    ) {
        parent::__construct();
    }

    protected function configureCommand(string $lifecycleMethod): void
    {
        $this
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
            )
            ->addOption(
                'clearCache',
                'c',
                InputOption::VALUE_NONE,
                'Use this option to clear the cache after executing the plugin command'
            )
            ->addOption(
                'skip-asset-build',
                null,
                InputOption::VALUE_NONE,
                'Use this option to skip asset building'
            );
    }

    protected function prepareExecution(
        string $lifecycleMethod,
        SymfonyStyle $io,
        InputInterface $input,
        Context $context
    ): ?PluginCollection {
        $io->title('Shopware Plugin Lifecycle Service');

        if ($input->getOption('refresh')) {
            $io->note('Refreshing plugin list');
            $this->refreshPlugins();
        }

        if ($input->getOption('skip-asset-build')) {
            $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
        }

        $plugins = $this->parsePluginArgument($input->getArgument('plugins'), $lifecycleMethod, $io, $context);

        if ($plugins === null) {
            return null;
        }

        if ($plugins->count() === 0) {
            $io->warning('No plugins found');
            $io->text('Try the plugin:refresh command first, run composer update for changes in the plugin\'s composer.json, or change your search term');

            return $plugins;
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

    protected function handleClearCacheOption(InputInterface $input, ShopwareStyle $io, string $action): void
    {
        if ($input->getOption('clearCache')) {
            $io->note('Clearing Cache');

            try {
                $this->cacheClearer->clear();
            } catch (\Exception) {
                $io->error('Error clearing cache');

                return;
            }
            $io->success('Cache cleared');

            return;
        }

        $io->note(
            sprintf(
                'You may want to clear the cache after %s plugin(s). To do so run the cache:clear command',
                $action
            )
        );
    }

    private function parsePluginArgument(
        array $arguments,
        string $lifecycleMethod,
        SymfonyStyle $io,
        Context $context
    ): ?PluginCollection {
        $plugins = array_unique($arguments);
        $filter = [];

        // try exact match first
        if (\count($plugins) === 1) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', $plugins[0]));

            /** @var PluginCollection $matches */
            $matches = $this->pluginRepo->search($criteria, $context)->getEntities();
            if ($matches->count() === 1) {
                return $matches;
            }
        }

        foreach ($plugins as $plugin) {
            $filter[] = new ContainsFilter('name', $plugin);
        }

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('name'));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $filter));

        /** @var PluginCollection $pluginCollection */
        $pluginCollection = $this->pluginRepo->search($criteria, $context)->getEntities();

        if ($pluginCollection->count() <= 1) {
            return $pluginCollection;
        }

        $choiceAbort = 'Cancel.';
        $choiceSelect = sprintf('Select one Plugin to %s.', $lifecycleMethod);

        $choice = $io->askQuestion(
            new ChoiceQuestion(
                sprintf(
                    '%d plugins were found. How do you want to continue?',
                    $pluginCollection->count()
                ),
                [
                    sprintf('%s all of them.', $lifecycleMethod),
                    $choiceSelect,
                    $choiceAbort,
                ]
            )
        );

        if ($choice === $choiceAbort) {
            $io->note('Aborting due to user input.');

            return null;
        }

        if ($choice === $choiceSelect) {
            $id = $io->askQuestion(
                new ChoiceQuestion(
                    sprintf(
                        'Which plugin do you want to %s?',
                        $lifecycleMethod
                    ),
                    $pluginCollection->map(fn (PluginEntity $plugin) => $plugin->getName())
                )
            );

            return new PluginCollection([$pluginCollection->get($id)]);
        }

        return $pluginCollection;
    }

    /**
     * @return array<string>
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
