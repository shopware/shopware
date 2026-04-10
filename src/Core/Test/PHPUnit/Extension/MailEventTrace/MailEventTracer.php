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

    public function install(): void
    {
        $this->traces = [];

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
        foreach ($this->allTraces as ['test' => $test, 'traces' => $traces]) {
            echo \PHP_EOL . \sprintf('[MailEventTrace] %s dispatched %d mail.sent event(s):', $test, \count($traces)) . \PHP_EOL;

            foreach ($traces as $i => $trace) {
                echo \sprintf('  #%d %s', $i + 1, $trace) . \PHP_EOL;
            }
        }
    }
}
