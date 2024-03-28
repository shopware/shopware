<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Staging\Command;

use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'system:setup:staging',
    description: 'Installs the Shopware 6 system in staging mode',
)]
#[Package('core')]
class SystemSetupStagingCommand extends Command
{
    public function __construct(
        readonly private EventDispatcherInterface $eventDispatcher,
        readonly private SystemConfigService $systemConfigService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        if (!$io->confirm('This command will install the Shopware 6 system in staging mode. It will overwrite existing data in this database, make sure you use a staging database and have a backup', false)) {
            return self::FAILURE;
        }

        $event = new SetupStagingEvent(Context::createCLIContext(), $io);
        $this->eventDispatcher->dispatch($event);

        $this->systemConfigService->set(SetupStagingEvent::CONFIG_FLAG, true);

        return $event->canceled ? self::FAILURE : self::SUCCESS;
    }
}
