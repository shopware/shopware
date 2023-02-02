<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Enqueue\Dbal\DbalContext;
use Shopware\Core\Framework\Test\TestCaseHelper\StopWorkerWhenIdleListener;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
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
        /** @var MessageBusInterface $bus */
        $bus = $this->getContainer()->get('messenger.bus.test_shopware');

        return $bus;
    }

    public function getReceiver(): ReceiverInterface
    {
        /** @var ServiceLocator $locator */
        $locator = $this->getContainer()->get('messenger.test_receiver_locator');
        /** @var ReceiverInterface $receiver */
        $receiver = $locator->get('default');

        return $receiver;
    }

    public function runWorker(): void
    {
        $bus = $this->getBus();

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerWhenIdleListener());

        $worker = new Worker([$this->getReceiver()], $bus, $eventDispatcher);

        $worker->run([
            'sleep' => 1000,
        ]);
    }

    abstract protected function getContainer(): ContainerInterface;
}
