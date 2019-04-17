<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginActivateCommand extends AbstractPluginLifecycleCommand
{
    private const LIFECYCLE_METHOD = 'activate';

    protected function configure(): void
    {
        $this->configureCommand(self::LIFECYCLE_METHOD);
    }

    /**
     * {@inheritdoc}
     *
     * @throws PluginNotInstalledException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();
        $plugins = $this->prepareExecution(self::LIFECYCLE_METHOD, $io, $input, $context);

        $activated = 0;
        /** @var PluginEntity $plugin */
        foreach ($plugins as $plugin) {
            if ($plugin->getInstalledAt() === null) {
                $io->note(sprintf('Plugin "%s" must be installed. Skipping.', $plugin->getLabel()));

                continue;
            }

            if ($plugin->getActive()) {
                $io->note(sprintf('Plugin "%s" is already active. Skipping.', $plugin->getLabel()));

                continue;
            }

            $this->pluginLifecycleService->activatePlugin($plugin, $context);
            ++$activated;

            $io->text(sprintf('Plugin "%s" has been activated successfully.', $plugin->getLabel()));
        }

        if ($activated !== 0) {
            $io->success(sprintf('Activated %d plugin(s).', $activated));
        }
    }
}
