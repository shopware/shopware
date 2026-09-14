<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\StateMachine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PdoException;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteCommandExceptionEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
class StateMachineRegistryTransactionTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Context $context;

    private StateMachineRegistry $registry;

    private EventDispatcherInterface $dispatcher;

    private string $orderId;

    private int $transitionEvents = 0;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->context = Context::createDefaultContext();
        $this->registry = $container->get(StateMachineRegistry::class);
        $this->dispatcher = $container->get('event_dispatcher');

        // Retries must own the outermost transaction, so these fixtures are explicitly cleaned up.
        static::assertSame(0, $this->connection->getTransactionNestingLevel());
        $ids = new IdsCollection();
        $orderNumber = Uuid::randomHex();
        $this->orderId = $ids->get($orderNumber);
        $container->get('order.repository')->create([(new OrderBuilder($ids, $orderNumber))->build()], $this->context);
    }

    protected function tearDown(): void
    {
        $this->resetEventDispatcher();

        try {
            static::assertSame(0, $this->connection->getTransactionNestingLevel());
        } finally {
            $this->connection->delete('state_machine_history', ['referenced_id' => Uuid::fromHexToBytes($this->orderId)]);
            $this->connection->delete('`order`', ['id' => Uuid::fromHexToBytes($this->orderId)]);
        }
    }

    #[DataProvider('writtenFailureProvider')]
    public function testWrittenFailureRollsBackStateAndHistoryAndAllowsAnExplicitRetry(string $entityName, \Throwable $failure): void
    {
        $writtenCalls = 0;
        $this->addEventListener($this->dispatcher, StateMachineTransitionEvent::class, function (): void {
            ++$this->transitionEvents;
        });
        $listener = static function (EntityWrittenContainerEvent $event) use ($entityName, $failure, &$writtenCalls): void {
            if ($event->getEventByEntityName($entityName) === null) {
                return;
            }

            ++$writtenCalls;

            throw $failure;
        };
        $this->dispatcher->addListener(EntityWrittenContainerEvent::class, $listener, 10000);

        try {
            try {
                $this->registry->transition(new Transition('order', $this->orderId, 'cancel', 'stateId'), $this->context);
                static::fail('Expected the written-event listener to fail.');
            } catch (\Throwable $exception) {
                static::assertSame($failure, $exception);
            }

            static::assertSame(1, $writtenCalls);
            static::assertSame('open', $this->readState());
            static::assertSame(0, $this->countHistory());
            static::assertSame(0, $this->transitionEvents);
        } finally {
            $this->dispatcher->removeListener(EntityWrittenContainerEvent::class, $listener);
        }

        $this->registry->transition(new Transition('order', $this->orderId, 'cancel', 'stateId'), $this->context);

        static::assertSame('cancelled', $this->readState());
        static::assertSame(1, $this->countHistory());
        static::assertSame(1, $this->transitionEvents);
    }

    /**
     * @return iterable<string, array{string, \Throwable}>
     */
    public static function writtenFailureProvider(): iterable
    {
        yield 'history listener failure' => ['state_machine_history', new \RuntimeException('History listener failed')];
        yield 'state listener failure' => ['order', new \RuntimeException('State listener failed')];
        yield 'retryable state listener failure must not replay listeners' => ['order', self::recordChangedException()];
    }

    public function testWriteContentionRetriesBeforeCallbacksAndDiscardsTransientErrors(): void
    {
        $attempts = 0;
        $successCalls = 0;
        $errorCalls = 0;
        $exceptionEvents = 0;
        $this->addEventListener($this->dispatcher, EntityWriteEvent::class, static function (EntityWriteEvent $event) use (&$successCalls, &$errorCalls): void {
            $event->addSuccess(static function () use (&$successCalls): void {
                ++$successCalls;
            });
            $event->addError(static function () use (&$errorCalls): void {
                ++$errorCalls;
            });
        });
        $this->addEventListener($this->dispatcher, WriteCommandExceptionEvent::class, static function () use (&$exceptionEvents): void {
            ++$exceptionEvents;
        });
        $this->addEventListener($this->dispatcher, PostWriteValidationEvent::class, static function () use (&$attempts): void {
            if (++$attempts === 1) {
                throw self::recordChangedException();
            }
        });

        $this->registry->transition(new Transition('order', $this->orderId, 'cancel', 'stateId'), $this->context);

        static::assertSame(2, $attempts);
        static::assertSame(1, $successCalls);
        static::assertSame(0, $errorCalls);
        static::assertSame(0, $exceptionEvents);
        static::assertSame('cancelled', $this->readState());
        static::assertSame(1, $this->countHistory());
    }

    public function testRetryExhaustionNotifiesTheFinalWriteErrorOnce(): void
    {
        $attempts = 0;
        $errorCalls = 0;
        $exceptionEvents = 0;
        $failure = self::recordChangedException();
        $this->addEventListener($this->dispatcher, EntityWriteEvent::class, static function (EntityWriteEvent $event) use (&$errorCalls): void {
            $event->addError(static function () use (&$errorCalls): void {
                ++$errorCalls;
            });
        });
        $this->addEventListener($this->dispatcher, WriteCommandExceptionEvent::class, static function () use (&$exceptionEvents): void {
            ++$exceptionEvents;
        });
        $this->addEventListener($this->dispatcher, PostWriteValidationEvent::class, static function () use (&$attempts, $failure): never {
            ++$attempts;

            throw $failure;
        });

        try {
            $this->registry->transition(new Transition('order', $this->orderId, 'cancel', 'stateId'), $this->context);
            static::fail('Expected the contention retries to be exhausted.');
        } catch (\Throwable $exception) {
            static::assertSame($failure, $exception);
        }

        static::assertSame(11, $attempts);
        static::assertSame(1, $errorCalls);
        static::assertSame(1, $exceptionEvents);
        static::assertSame('open', $this->readState());
        static::assertSame(0, $this->countHistory());
    }

    public function testWriteSuccessCallbackFailureIsRolledBackWithoutReplayingCallbacks(): void
    {
        $successCalls = 0;
        $errorCalls = 0;
        $failure = self::recordChangedException();
        $this->addEventListener($this->dispatcher, EntityWriteEvent::class, static function (EntityWriteEvent $event) use (&$successCalls, &$errorCalls, $failure): void {
            $event->addSuccess(static function () use (&$successCalls, $failure): never {
                ++$successCalls;

                throw $failure;
            });
            $event->addError(static function () use (&$errorCalls): void {
                ++$errorCalls;
            });
        });

        try {
            $this->registry->transition(new Transition('order', $this->orderId, 'cancel', 'stateId'), $this->context);
            static::fail('Expected the write-success callback to fail.');
        } catch (\Throwable $exception) {
            static::assertSame($failure, $exception);
        }

        static::assertSame(1, $successCalls);
        static::assertSame(1, $errorCalls);
        static::assertSame('open', $this->readState());
        static::assertSame(0, $this->countHistory());
    }

    private static function recordChangedException(): DriverException
    {
        return new DriverException(new PdoException('Record has changed since last read', 'HY000', 1020), null);
    }

    private function readState(): string
    {
        return $this->connection->fetchOne(
            'SELECT state.technical_name FROM `order` INNER JOIN state_machine_state state ON state.id = `order`.state_id WHERE `order`.id = :id AND `order`.version_id = :version',
            ['id' => Uuid::fromHexToBytes($this->orderId), 'version' => Uuid::fromHexToBytes($this->context->getVersionId())]
        );
    }

    private function countHistory(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM state_machine_history WHERE referenced_id = :id', ['id' => Uuid::fromHexToBytes($this->orderId)]);
    }
}
