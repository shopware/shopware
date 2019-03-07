<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\Framework\Test\TestCaseHelper\RunUntilEmptyReceiver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

trait QueueTestBehaviour
{
    abstract public function getContainer(): ContainerInterface;

    /**
     * @before
     */
    public function clearQueue()
    {
        if (file_exists($this->getQueueFile())) {
            file_put_contents($this->getQueueFile(), '');
        }
    }

    /**
     * @after
     */
    public function removeQueue()
    {
        if (file_exists($this->getQueueFile())) {
            unlink($this->getQueueFile());
        }
    }

    public function getQueueFile(): string
    {
        $path = $this->getContainer()->get('enqueue.client.default.config')->getTransportOption('dsn');
        $path = str_replace('file://', '', $path);

        return $path . '/messages';
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
        $decoratedReceiver = new RunUntilEmptyReceiver($this->getReceiver(), $this->getQueueFile());

        $bus = $this->getBus();

        $worker = new Worker($decoratedReceiver, $bus);
        $worker->run();
    }
}
