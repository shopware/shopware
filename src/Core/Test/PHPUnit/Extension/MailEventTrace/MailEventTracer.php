<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\MailEventTrace;

use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class MailEventTracer
{
    /**
     * @var list<array{test: string, traces: list<string>}>
     */
    private array $allTraces = [];

    /**
     * @var list<string>
     */
    private array $currentTraces = [];

    private ?EventDispatcherInterface $dispatcher = null;

    private ?\Closure $listener = null;

    private bool $stopped = false;

    public function __construct(private readonly string $stopBeforeTest = '')
    {
    }

    public function install(string $testId): void
    {
        if ($this->stopped) {
            return;
        }

        if ($this->stopBeforeTest !== '' && $testId === $this->stopBeforeTest) {
            $this->stopped = true;

            return;
        }

        $this->currentTraces = [];

        try {
            $container = KernelLifecycleManager::getKernel()->getContainer();
            $this->dispatcher = $container->get('event_dispatcher');
        } catch (\Throwable) {
            return;
        }

        // If the test already registered a MailSentEvent listener in setUp,
        // it is explicitly handling mail — skip tracing for this test.
        if ($this->dispatcher->getListeners(MailSentEvent::class) !== []) {
            $this->dispatcher = null;

            return;
        }

        $this->listener = function (MailSentEvent $event): void {
            $frames = \array_filter(
                \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS),
                static fn (array $frame): bool => isset($frame['file']) && \str_contains($frame['file'], \DIRECTORY_SEPARATOR . 'tests' . \DIRECTORY_SEPARATOR),
            );

            $trace = \implode("\n", \array_map(
                static fn (array $frame): string => \sprintf('  %s(%d)', $frame['file'], $frame['line'] ?? 0),
                \array_values($frames),
            ));

            $this->currentTraces[] = \sprintf("Subject: %s\n%s", $event->getSubject(), $trace);
        };

        $this->dispatcher->addListener(MailSentEvent::class, $this->listener, \PHP_INT_MAX);
    }

    public function collectAndUninstall(string $testDescription): void
    {
        if ($this->dispatcher !== null && $this->listener !== null) {
            $this->dispatcher->removeListener(MailSentEvent::class, $this->listener);
        }

        if ($this->currentTraces !== []) {
            $this->allTraces[] = ['test' => $testDescription, 'traces' => $this->currentTraces];
        }

        $this->currentTraces = [];
        $this->dispatcher = null;
        $this->listener = null;
    }

    public function report(): void
    {
        if ($this->allTraces === []) {
            return;
        }

        // Deduplicate: group by unique call stack so the same source location
        // shared across many tests collapses into one entry with a count.
        /** @var array<string, array{subjects: list<string>, count: int, example: string}> $groups */
        $groups = [];

        foreach ($this->allTraces as ['test' => $test, 'traces' => $traces]) {
            foreach ($traces as $trace) {
                [$subject, $frames] = \explode("\n", $trace, 2) + [1 => ''];

                if (!isset($groups[$frames])) {
                    $groups[$frames] = ['subjects' => [], 'count' => 0, 'example' => $test];
                }

                ++$groups[$frames]['count'];

                if (!\in_array($subject, $groups[$frames]['subjects'], true)) {
                    $groups[$frames]['subjects'][] = $subject;
                }
            }
        }

        foreach ($groups as $frames => $group) {
            echo \PHP_EOL . \sprintf('[MailEventTrace] %dx — e.g. %s', $group['count'], $group['example']) . \PHP_EOL;
            foreach ($group['subjects'] as $subject) {
                echo '  ' . $subject . \PHP_EOL;
            }
            echo $frames . \PHP_EOL;
        }
    }
}
