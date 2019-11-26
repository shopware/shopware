<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Shopware\Core\Framework\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PluginUpdateCommand extends AbstractPluginLifecycleCommand
{
    private const LIFECYCLE_METHOD = 'update';

    protected static $defaultName = 'plugin:update';

    protected function configure(): void
    {
        $this->configureCommand(self::LIFECYCLE_METHOD);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new ShopwareStyle($input, $output);
        $context = Context::createDefaultContext();
        $plugins = $this->prepareExecution(self::LIFECYCLE_METHOD, $io, $input, $context);

        if ($plugins === null) {
            return null;
        }

        $updatedPluginCount = 0;
        foreach ($plugins as $plugin) {
            $this->pluginLifecycleService->updatePlugin($plugin, $context);
            ++$updatedPluginCount;

            $io->text(sprintf('Plugin "%s" has been updated successfully.', $plugin->getName()));
        }

        if ($updatedPluginCount !== 0) {
            $io->success(sprintf('Updated %d plugin(s).', $updatedPluginCount));
        }

        $this->handleClearCacheOption($input, $io, 'updating');

        return null;
    }
}
