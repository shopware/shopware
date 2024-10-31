<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'plugin:checksum:check',
    description: 'Check the integrity of plugin files',
)]
#[Package('core')]
class PluginChecksumCheckCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $pluginRepository,
        private readonly PluginFileHashService $pluginFileHashService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('plugin', InputArgument::OPTIONAL, 'Plugin name');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $plugins = $this->getPlugins((string) $input->getArgument('plugin'), $io);
        if ($plugins->count() === 0) {
            $io->error('No plugins found');

            return self::FAILURE;
        }

        $success = true;
        foreach ($plugins as $plugin) {
            $io->info('Checking plugin: ' . $plugin->getName());

            $pluginChecksumCheckResult = $this->pluginFileHashService->checkPluginForChanges($plugin);
            if ($pluginChecksumCheckResult->isFileMissing()) {
                $io->info(\sprintf('Plugin "%s" checksum file for not found', $plugin->getName()));

                continue;
            }

            if ($pluginChecksumCheckResult->isWrongVersion()) {
                $io->error(\sprintf('Checksum file for plugin "%s" was generated for different version', $plugin->getName()));

                continue;
            }

            if ($pluginChecksumCheckResult->getNewFiles() === []
                && $pluginChecksumCheckResult->getChangedFiles() === []
                && $pluginChecksumCheckResult->getMissingFiles() === []
            ) {
                $io->success(\sprintf('Plugin "%s" has no detected file-changes.', $plugin->getName()));

                continue;
            }

            $success = false;

            $io->error(\sprintf('Plugin "%s" has changed code.', $plugin->getName()));
            $this->outputFileChanges($io, 'New files detected:', $pluginChecksumCheckResult->getNewFiles());
            $this->outputFileChanges($io, 'Changed files detected:', $pluginChecksumCheckResult->getChangedFiles());
            $this->outputFileChanges($io, 'Missing files detected:', $pluginChecksumCheckResult->getMissingFiles());
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    private function getPlugins(string $pluginName, ShopwareStyle $io): PluginCollection
    {
        $context = Context::createCLIContext();

        if (!$pluginName) {
            $io->info('Checking all plugins');

            /** @var PluginCollection $plugins */
            $plugins = $this->pluginRepository->search(new Criteria(), $context)->getEntities();

            return $plugins;
        }

        $plugins = new PluginCollection();

        $plugin = $this->getPlugin($pluginName, $context);
        if ($plugin instanceof PluginEntity) {
            $plugins->add($plugin);
        }

        return $plugins;
    }

    private function getPlugin(string $pluginName, Context $context): ?Entity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $pluginName));

        return $this->pluginRepository->search($criteria, $context)->first();
    }

    /**
     * @param string[] $files
     */
    private function outputFileChanges(ShopwareStyle $io, string $text, array $files): void
    {
        if ($files) {
            $io->warning($text);
            $io->listing($files);
        }
    }
}
