<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Command;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Update\Event\UpdatePostPrepareEvent;
use Shopware\Core\Framework\Update\Event\UpdatePrePrepareEvent;
use Shopware\Core\Kernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'system:update:prepare',
    description: 'Prepares the update process',
)]
#[Package('core')]
class SystemUpdatePrepareCommand extends Command
{
    public function __construct(private readonly ContainerInterface $container)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new ShopwareStyle($input, $output);

        $dsn = trim((string) (EnvironmentHelper::getVariable('DATABASE_URL', getenv('DATABASE_URL'))));
        if ($dsn === '') {
            $output->note('Environment variable \'DATABASE_URL\' not defined. Skipping ' . $this->getName() . '...');

            return self::SUCCESS;
        }

        $output->writeln('Run Update preparations');

        $context = Context::createDefaultContext();
        $currentVersion = $this->container->getParameter('kernel.shopware_version');
        if (!\is_string($currentVersion)) {
            throw new \RuntimeException('Container parameter "kernel.shopware_version" needs to be a string');
        }

        // TODO: get new version (from composer.lock?)
        $newVersion = '';

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->container->get('event_dispatcher');
        $eventDispatcher->dispatch(new UpdatePrePrepareEvent($context, $currentVersion, $newVersion));

        /** @var EventDispatcherInterface $eventDispatcherWithoutPlugins */
        $eventDispatcherWithoutPlugins = $this->rebootKernelWithoutPlugins()->get('event_dispatcher');

        // @internal plugins are deactivated
        $eventDispatcherWithoutPlugins->dispatch(new UpdatePostPrepareEvent($context, $currentVersion, $newVersion));

        return self::SUCCESS;
    }

    private function rebootKernelWithoutPlugins(): ContainerInterface
    {
        /** @var Kernel $kernel */
        $kernel = $this->container->get('kernel');

        $classLoad = $kernel->getPluginLoader()->getClassLoader();
        $kernel->reboot(null, new StaticKernelPluginLoader($classLoad));

        return $kernel->getContainer();
    }
}
