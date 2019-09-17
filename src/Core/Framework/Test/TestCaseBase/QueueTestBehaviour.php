<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Enqueue\Dbal\DbalContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

trait QueueTestBehaviour
{
    /**
     * @before
     * @after
     */
    public function clearQueue(): void
    {
        /** @var DbalContext $context */
        $context = $this->getContainer()->get('enqueue.transport.default.context');
        $context->purgeQueue($context->createQueue('messages'));
    }

    public function getBus(): MessageBusInterface
    {
        return $this->getContainer()->get('messenger.bus.test_shopware');
    }

    public function getReceiver(): ReceiverInterface
    {
        return $this->getContainer()->get('messenger.test_receiver_locator')->get('default');
    }

    public function runWorker(): void
    {
        $bus = $this->getBus();

        $worker = new Worker([$this->getReceiver()], $bus);
        $worker->run([
            'sleep' => 1000,
        ], function (?Envelope $envelope) use ($worker): void {
            if ($envelope === null) {
                $worker->stop();
            }
        });
    }

    abstract protected function getContainer(): ContainerInterface;
}
