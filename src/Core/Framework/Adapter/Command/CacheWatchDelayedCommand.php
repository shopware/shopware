<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Package('core')]
#[AsCommand(name: 'cache:watch:delayed', description: 'Watches the delayed cache keys/tags')]
class CacheWatchDelayedCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ContainerInterface $container
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface) {
            $output->write('This command is only available in console context.');

            return self::FAILURE;
        }

        $this->dispatcher->addListener(ConsoleEvents::SIGNAL, function (ConsoleSignalEvent $event): void {
            $signal = $event->getHandlingSignal();
            $event->setExitCode(0);

            if ($signal === \SIGINT) {
                $event->getOutput()->writeln('Cache is now on its own.. bye!');
            }
        });

        $before = $this->container
            ->get('shopware.cache.invalidator.storage.redis_adapter')
            ->sMembers('invalidation');

        $section = $output->section();

        $table = new Table($section);
        $this->render($table, $before);

        // @phpstan-ignore-next-line
        while (true) {
            $current = $this->container
                ->get('shopware.cache.invalidator.storage.redis_adapter')
                ->sMembers('invalidation');

            if ($before !== $current) {
                $section->clear();
                $this->render($table, $current);
                $before = $current;
            }

            usleep(1000);
        }
    }

    /**
     * @param array<string> $rows
     */
    private function render(Table $table, array $rows): void
    {
        $table->setHeaders(['Tags at: ' . date('Y-m-d H:i:s')]);
        $table->setRows(array_map(fn ($tag) => [$tag], $rows));
        $table->render();
    }
}
