<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'plugin:update',
    description: 'Updates a plugin',
)]
#[Package('core')]
class PluginUpdateCommand extends AbstractPluginLifecycleCommand
{
    private const LIFECYCLE_METHOD = 'update';

    protected function configure(): void
    {
        $this->configureCommand(self::LIFECYCLE_METHOD);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $context = Context::createDefaultContext();
        $plugins = $this->prepareExecution(self::LIFECYCLE_METHOD, $io, $input, $context);

        if ($plugins === null) {
            return self::SUCCESS;
        }

        $updatedPluginCount = 0;
        foreach ($plugins as $plugin) {
            if ($plugin->getInstalledAt() === null) {
                $io->note(sprintf('Plugin "%s" is not installed. Skipping.', $plugin->getName()));

                continue;
            }
            $this->pluginLifecycleService->updatePlugin($plugin, $context);
            ++$updatedPluginCount;

            $io->text(sprintf('Plugin "%s" has been updated successfully.', $plugin->getName()));
        }

        if ($updatedPluginCount !== 0) {
            $io->success(sprintf('Updated %d plugin(s).', $updatedPluginCount));
        }

        $this->handleClearCacheOption($input, $io, 'updating');

        return self::SUCCESS;
    }
}
