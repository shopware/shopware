<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\Api\ConsumeMessagesController;
use Shopware\Core\Framework\MessageQueue\MessageQueueException;
use Shopware\Core\Framework\MessageQueue\Subscriber\EarlyReturnMessagesListener;
use Shopware\Core\Framework\MessageQueue\Subscriber\MessageQueueStatsSubscriber;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(ConsumeMessagesController::class)]
class ConsumeMessagesControllerTest extends TestCase
{
    public function testInvalidReceiver(): void
    {
        $controller = new ConsumeMessagesController(
            new ServiceLocator([]),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(StopWorkerOnRestartSignalListener::class),
            $this->createMock(EarlyReturnMessagesListener::class),
            $this->createMock(MessageQueueStatsSubscriber::class),
            'async',
            '128M',
            20,
            $this->createMock(LockFactory::class)
        );

        static::expectException(MessageQueueException::class);
        static::expectExceptionMessage('No receiver name provided.');

        $controller->consumeMessages(new Request());
    }

    public function testLocked(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(false);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory
            ->method('createLock')
            ->willReturn($lock);

        $controller = new ConsumeMessagesController(
            new ServiceLocator(['async' => function (): \ArrayObject {
                return new \ArrayObject();
            }]),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(StopWorkerOnRestartSignalListener::class),
            $this->createMock(EarlyReturnMessagesListener::class),
            $this->createMock(MessageQueueStatsSubscriber::class),
            'async',
            '128M',
            20,
            $lockFactory
        );

        static::expectException(MessageQueueException::class);
        static::expectExceptionMessage('Another worker is already running for receiver: "async"');

        $request = new Request();
        $request->query->set('receiver', 'async');
        $controller->consumeMessages($request);
    }
}
