<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deactivates or uninstalls every plugin which is not shipped by Shopware.
 *
 * This is a troubleshooting command: it brings a shop that is broken by a
 * third-party plugin back to life, so that the causing plugin can be found
 * by re-enabling one plugin at a time.
 */
#[Package('framework')]
#[AsCommand(
    name: 'plugin:debug:mode',
    description: 'Deactivates all plugins which are not from Shopware, optionally uninstalls them while keeping user data',
)]
class PluginDebugModeCommand extends Command
{
    /**
     * Plugin authors of Shopware's own plugins.
     *
     * The author is free text from the plugin's composer.json, so the composer
     * package name is checked as well.
     *
     * @var list<string>
     */
    private const SHOPWARE_AUTHORS = ['shopware ag', 'shopware', 'swag'];

    private const SHOPWARE_COMPOSER_PREFIX = 'shopware/';

    /**
     * @internal
     *
     * @param EntityRepository<PluginCollection> $pluginRepo
     */
    public function __construct(
        private readonly EntityRepository $pluginRepo,
        private readonly PluginLifecycleService $pluginLifecycleService,
        private readonly CacheClearer $cacheClearer,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption(
            'uninstall',
            null,
            InputOption::VALUE_NONE,
            'Uninstall the plugins instead of deactivating them. User data is kept.'
        );
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation');
        $this->addOption('clearCache', 'c', InputOption::VALUE_NONE, 'Clear the cache after the plugins have been changed');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createCLIContext();
        $uninstall = (bool) $input->getOption('uninstall');

        $plugins = $this->pluginRepo
            ->search($this->createCriteria($uninstall), $context)
            ->getEntities();

        $thirdPartyPlugins = array_values(array_filter(
            $plugins->getElements(),
            fn (PluginEntity $plugin): bool => !$this->isShopwarePlugin($plugin)
        ));

        if ($thirdPartyPlugins === []) {
            $io->note('No plugins found which are not from Shopware.');

            return self::SUCCESS;
        }

        $action = $uninstall ? 'uninstall' : 'deactivate';
        $pastTense = $uninstall ? 'Uninstalled' : 'Deactivated';

        $io->text(\sprintf('The following %d plugin(s) are not from Shopware:', \count($thirdPartyPlugins)));
        $io->listing(array_map(
            static fn (PluginEntity $plugin): string => \sprintf('%s (%s)', $plugin->getName(), $plugin->getAuthor() ?? 'unknown author'),
            $thirdPartyPlugins
        ));

        if (!$input->getOption('force') && !$io->confirm(\sprintf('Do you really want to %s these plugins?', $action), false)) {
            $io->note('Aborted.');

            return self::SUCCESS;
        }

        $changedPlugins = 0;
        $failedPlugins = [];

        foreach ($thirdPartyPlugins as $plugin) {
            try {
                if ($uninstall) {
                    $this->pluginLifecycleService->uninstallPlugin($plugin, $context, true);
                } else {
                    $this->pluginLifecycleService->deactivatePlugin($plugin, $context);
                }

                ++$changedPlugins;

                $io->text(\sprintf('Plugin "%s" has been %s successfully.', $plugin->getName(), strtolower($pastTense)));
            } catch (\Throwable $exception) {
                // A single broken plugin must not stop the command from rescuing the shop.
                $failedPlugins[$plugin->getName()] = $exception->getMessage();
            }
        }

        if ($changedPlugins !== 0) {
            $io->success(\sprintf('%s %d plugin(s).', $pastTense, $changedPlugins));
        }

        if ($failedPlugins !== []) {
            $io->error(\sprintf('%d plugin(s) could not be %s:', \count($failedPlugins), strtolower($pastTense)));
            $io->listing(array_map(
                static fn (string $name, string $message): string => \sprintf('%s: %s', $name, $message),
                array_keys($failedPlugins),
                array_values($failedPlugins)
            ));
        }

        $this->handleClearCache($input, $io);

        return $failedPlugins === [] ? self::SUCCESS : self::FAILURE;
    }

    private function createCriteria(bool $uninstall): Criteria
    {
        $criteria = new Criteria();

        if ($uninstall) {
            $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('installedAt', null)]));
        } else {
            $criteria->addFilter(new EqualsFilter('active', true));
        }

        $criteria->addSorting(new FieldSorting('name'));

        return $criteria;
    }

    private function isShopwarePlugin(PluginEntity $plugin): bool
    {
        $composerName = $plugin->getComposerName();
        if ($composerName !== null && str_starts_with($composerName, self::SHOPWARE_COMPOSER_PREFIX)) {
            return true;
        }

        $author = $plugin->getAuthor();
        if ($author === null) {
            return false;
        }

        return \in_array(strtolower(trim($author)), self::SHOPWARE_AUTHORS, true);
    }

    private function handleClearCache(InputInterface $input, SymfonyStyle $io): void
    {
        if (!$input->getOption('clearCache')) {
            $io->note('You may want to clear the cache after the plugins have been changed. To do so run the cache:clear command.');

            return;
        }

        $io->note('Clearing cache');

        try {
            $this->cacheClearer->clear();
        } catch (\Throwable $exception) {
            $io->error('Error clearing cache: ' . $exception->getMessage());

            return;
        }

        $io->success('Cache cleared');
    }
}
