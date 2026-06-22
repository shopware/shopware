<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Command;

use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Core\Framework\Adapter\Command\CacheWatchDelayedCommandTest;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @phpstan-import-type RedisTypeHint from RedisConnectionFactory
 */
#[Package('framework')]
#[AsCommand(name: 'cache:watch:delayed', description: 'Watches the delayed cache keys/tags')]
class CacheWatchDelayedCommand extends Command implements SignalableCommandInterface
{
    private const DEFAULT_POLL_INTERVAL_MICROSECONDS = 1000;
    private const MIN_POLL_INTERVAL_MICROSECONDS = 1;

    private bool $shouldStop = false;

    private ?OutputInterface $output = null;

    /**
     * @internal
     */
    public function __construct(private readonly ContainerInterface $container)
    {
        parent::__construct();
    }

    /**
     * @return array<int>
     */
    public function getSubscribedSignals(): array
    {
        return [\SIGINT, \SIGTERM];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->shouldStop = true;

        if ($signal === \SIGINT && $this->output !== null) {
            $this->output->writeln('Cache is now on its own.. bye!');
        }

        // Let execute() leave its loop and return SUCCESS rather than forcing an exit here.
        return false;
    }

    protected function configure(): void
    {
        $this->addOption(
            'interval',
            null,
            InputOption::VALUE_REQUIRED,
            \sprintf(
                'Poll interval in microseconds (%d-%d).',
                self::MIN_POLL_INTERVAL_MICROSECONDS,
                self::DEFAULT_POLL_INTERVAL_MICROSECONDS,
            ),
            self::DEFAULT_POLL_INTERVAL_MICROSECONDS,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface) {
            $output->write('This command is only available in console context.');

            return self::FAILURE;
        }

        if (!$this->container->has('shopware.cache.invalidator.storage.redis_adapter')) {
            $output->writeln('Redis cache invalidation is not configured.');

            return self::FAILURE;
        }

        /** @var RedisTypeHint $adapter */
        $adapter = $this->container->get('shopware.cache.invalidator.storage.redis_adapter');

        if (method_exists($adapter, 'sMembers') === false) {
            $output->writeln('Redis adapter does not support sMembers method.');

            return self::FAILURE;
        }

        $this->output = $output;

        $interval = $this->resolveInterval((int) $input->getOption('interval'));
        $section = $output->section();
        $table = new Table($section);

        $this->watch(static fn (): array => $adapter->sMembers('invalidation'), $section, $table, $interval);

        return self::SUCCESS;
    }

    /**
     * The watch loop only returns once a signal flips $shouldStop, so it cannot be reached in-process.
     *
     * @codeCoverageIgnore
     *
     * @see CacheWatchDelayedCommandTest
     *
     * @param callable(): array<string> $poll
     */
    private function watch(callable $poll, ConsoleSectionOutput $section, Table $table, int $interval): void
    {
        $before = $poll();
        $this->render($table, $before);

        while (!$this->shouldStop) {
            $current = $poll();

            if ($before !== $current) {
                $section->clear();
                $this->render($table, $current);
                $before = $current;
            }

            usleep($interval);
        }
    }

    /**
     * Clamps the requested poll interval into the supported range.
     */
    private function resolveInterval(int $microseconds): int
    {
        return clamp(
            $microseconds,
            self::MIN_POLL_INTERVAL_MICROSECONDS,
            self::DEFAULT_POLL_INTERVAL_MICROSECONDS,
        );
    }

    /**
     * @param array<string> $rows
     */
    private function render(Table $table, array $rows): void
    {
        $table->setHeaders(['Tags at: ' . date('Y-m-d H:i:s')]);
        $table->setRows(array_map(static fn ($tag) => [$tag], $rows));
        $table->render();
    }
}
