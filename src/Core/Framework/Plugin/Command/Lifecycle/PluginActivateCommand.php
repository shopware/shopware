<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PluginActivateCommand extends AbstractPluginLifecycleCommand
{
    private const LIFECYCLE_METHOD = 'activate';

    protected static $defaultName = 'plugin:activate';

    protected function configure(): void
    {
        $this->configureCommand(self::LIFECYCLE_METHOD);
    }

    /**
     * {@inheritdoc}
     *
     * @throws PluginNotInstalledException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $context = Context::createDefaultContext();
        $plugins = $this->prepareExecution(self::LIFECYCLE_METHOD, $io, $input, $context);

        if ($plugins === null) {
            return self::SUCCESS;
        }

        $activatedPluginCount = 0;
        foreach ($plugins as $plugin) {
            if ($plugin->getInstalledAt() === null) {
                $io->note(sprintf('Plugin "%s" must be installed. Skipping.', $plugin->getName()));

                continue;
            }

            if ($plugin->getActive()) {
                $io->note(sprintf('Plugin "%s" is already active. Skipping.', $plugin->getName()));

                continue;
            }

            $this->pluginLifecycleService->activatePlugin($plugin, $context);
            ++$activatedPluginCount;

            $io->text(sprintf('Plugin "%s" has been activated successfully.', $plugin->getName()));
        }

        if ($activatedPluginCount !== 0) {
            $io->success(sprintf('Activated %d plugin(s).', $activatedPluginCount));
        }

        $this->handleClearCacheOption($input, $io, 'activating');

        return self::SUCCESS;
    }
}
