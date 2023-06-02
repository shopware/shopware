<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\MessageQueue\Subscriber\MessageQueueStatsSubscriber;
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
     *
     * @after
     */
    public function clearQueue(): void
    {
        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM messenger_messages');
    }

    public function runWorker(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerWhenIdleListener());
        $eventDispatcher->addSubscriber($this->getContainer()->get(MessageQueueStatsSubscriber::class));

        /** @var ServiceLocator<ReceiverInterface> $locator */
        $locator = $this->getContainer()->get('messenger.test_receiver_locator');

        /** @var ReceiverInterface $receiver */
        $receiver = $locator->get('async');

        /** @var MessageBusInterface $bus */
        $bus = $this->getContainer()->get('messenger.bus.test_shopware');

        $worker = new Worker([$receiver], $bus, $eventDispatcher);

        $worker->run([
            'sleep' => 1000,
        ]);
    }

    abstract protected static function getContainer(): ContainerInterface;
}
