<?php

declare(strict_types=1);

namespace Shopware\Core\DevOps\System\Command;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Update\Api\UpdateController;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Update\Event\UpdatePreFinishEvent;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SystemUpdateFinishCommand extends Command
{
    public static $defaultName = 'system:update:finish';

    private ContainerInterface $container;

    /**
     * @psalm-suppress ContainerDependency
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new ShopwareStyle($input, $output);

        $dsn = trim((string) (EnvironmentHelper::getVariable('DATABASE_URL', getenv('DATABASE_URL'))));
        if ($dsn === '' || $dsn === Kernel::PLACEHOLDER_DATABASE_URL) {
            $output->note("Environment variable 'DATABASE_URL' not defined. Skipping " . $this->getName() . '...');

            return self::SUCCESS;
        }

        $output->writeln('Run Post Update');
        $output->writeln('');

        $containerWithoutPlugins = $this->rebootKernelWithoutPlugins();

        $context = Context::createDefaultContext();
        $systemConfigService = $this->container->get(SystemConfigService::class);
        $oldVersion = $systemConfigService->getString(UpdateController::UPDATE_PREVIOUS_VERSION_KEY);

        $newVersion = $containerWithoutPlugins->getParameter('kernel.shopware_version');
        if (!\is_string($newVersion)) {
            throw new \RuntimeException('Container parameter "kernel.shopware_version" needs to be a string');
        }

        /** @var EventDispatcherInterface $eventDispatcherWithoutPlugins */
        $eventDispatcherWithoutPlugins = $this->rebootKernelWithoutPlugins()->get('event_dispatcher');
        $eventDispatcherWithoutPlugins->dispatch(new UpdatePreFinishEvent($context, $oldVersion, $newVersion));

        $this->runMigrations($output);

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->container->get('event_dispatcher');
        $updateEvent = new UpdatePostFinishEvent($context, $oldVersion, $newVersion);
        $eventDispatcher->dispatch($updateEvent);

        $this->installAssets($output);

        $output->writeln('');

        return self::SUCCESS;
    }

    private function runMigrations(OutputInterface $output): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            throw new \RuntimeException('No application initialised');
        }
        $command = $application->find('database:migrate');

        $arguments = [
            'identifier' => 'core',
            '--all' => true,
        ];
        $arrayInput = new ArrayInput($arguments, $command->getDefinition());

        return $command->run($arrayInput, $output);
    }

    private function installAssets(OutputInterface $output): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            throw new \RuntimeException('No application initialised');
        }
        $command = $application->find('assets:install');

        return $command->run(new ArrayInput([], $command->getDefinition()), $output);
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
