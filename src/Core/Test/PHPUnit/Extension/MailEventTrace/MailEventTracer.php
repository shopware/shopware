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
    /** @var list<string> */
    private array $traces = [];

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

        $this->listener = function (MailSentEvent $event): void {
            $this->traces[] = \sprintf(
                "Subject: %s\n%s",
                $event->getSubject(),
                (new \Exception())->getTraceAsString(),
            );
        };

        $this->dispatcher->addListener(MailSentEvent::class, $this->listener, \PHP_INT_MAX);
    }

    public function reportAndUninstall(string $testDescription): void
    {
        if ($this->dispatcher !== null && $this->listener !== null) {
            $this->dispatcher->removeListener(MailSentEvent::class, $this->listener);
        }

        if ($this->traces !== []) {
            echo \PHP_EOL . \sprintf('[MailEventTrace] %s dispatched %d mail.sent event(s):', $testDescription, \count($this->traces)) . \PHP_EOL;

            foreach ($this->traces as $i => $trace) {
                echo \sprintf('  #%d %s', $i + 1, $trace) . \PHP_EOL;
            }
        }

        $this->traces = [];
        $this->dispatcher = null;
        $this->listener = null;
    }
}
