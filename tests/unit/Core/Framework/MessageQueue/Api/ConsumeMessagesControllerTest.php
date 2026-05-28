<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Increment\AbstractIncrementer;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\MessageQueue\Api\ConsumeMessagesController;
use Shopware\Core\Framework\MessageQueue\MessageQueueException;
use Shopware\Core\Framework\MessageQueue\Stats\AbstractStatsRepository;
use Shopware\Core\Framework\MessageQueue\Stats\StatsService;
use Shopware\Core\Framework\MessageQueue\Subscriber\EarlyReturnMessagesListener;
use Shopware\Core\Framework\MessageQueue\Subscriber\MessageQueueStatsSubscriber;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

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
            new ServiceLocator(['async' => static function (): \ArrayObject {
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
        $request->request->set('receiver', 'async');
        $controller->consumeMessages($request);
    }

    public function testWorkerDoesNotBusyPollReceiverDuringLongPollAfterHandlingMessage(): void
    {
        $receiver = new class implements ReceiverInterface {
            public int $getCalls = 0;

            private bool $messageSent = false;

            public function get(): iterable
            {
                ++$this->getCalls;

                if ($this->messageSent) {
                    return [];
                }

                $this->messageSent = true;

                return [new Envelope(new \stdClass())];
            }

            public function ack(Envelope $envelope): void
            {
            }

            public function reject(Envelope $envelope): void
            {
            }
        };

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock
            ->expects($this->once())
            ->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory
            ->method('createLock')
            ->willReturn($lock);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus
            ->method('dispatch')
            ->willReturnCallback(static fn (Envelope $envelope): Envelope => $envelope);

        $controller = new ConsumeMessagesController(
            new ServiceLocator(['async' => static fn (): ReceiverInterface => $receiver]),
            $bus,
            new StopWorkerOnRestartSignalListener(new NullAdapter()),
            new EarlyReturnMessagesListener(),
            $this->createMessageQueueStatsSubscriber(),
            'async',
            '-1',
            1,
            $lockFactory
        );

        $request = new Request();
        $request->request->set('receiver', 'async');

        $response = $controller->consumeMessages($request);

        static::assertSame('{"handledMessages":1}', $response->getContent());
        static::assertLessThan(100, $receiver->getCalls);
    }

    private function createMessageQueueStatsSubscriber(): MessageQueueStatsSubscriber
    {
        $incrementer = new class extends AbstractIncrementer {
            public function __construct()
            {
                $this->setPool(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
            }

            public function decrement(string $cluster, string $key): void
            {
            }

            public function increment(string $cluster, string $key): void
            {
            }

            public function list(string $cluster, int $limit = 5, int $offset = 0): array
            {
                return [];
            }

            public function reset(string $cluster, ?string $key = null): void
            {
            }
        };

        return new MessageQueueStatsSubscriber(
            new IncrementGatewayRegistry([$incrementer]),
            new StatsService($this->createMock(AbstractStatsRepository::class), false)
        );
    }
}
