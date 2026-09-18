<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PdoException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableWriteTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteCommandExceptionEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Doctrine\FakeConnection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityWriteGateway::class)]
class EntityWriteGatewayTest extends TestCase
{
    private readonly EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    public function testImmutableFieldChangeThrowsViolation(): void
    {
        $gateway = $this->createGateway();
        $context = WriteContext::createFromContext(Context::createDefaultContext());
        $command = $this->createUpdateCommand('updated', 'initial');

        $exception = new WriteException();

        $violationList = new ConstraintViolationList();
        $violationList->add(
            new ConstraintViolation(
                'The field "immutable_field" of "immutable_test" is immutable and cannot be updated.',
                'The field "immutable_field" of "immutable_test" is immutable and cannot be updated.',
                [
                    'field' => 'immutable_field',
                    'entity' => 'immutable_test',
                ],
                'initial',
                'immutable_field',
                'updated'
            )
        );

        $exception->add(new WriteConstraintViolationException($violationList));
        static::expectExceptionObject($exception);

        $gateway->execute([$command], $context);
    }

    public function testImmutableFieldSameValueIsIgnored(): void
    {
        $gateway = $this->createGateway();
        $context = WriteContext::createFromContext(Context::createDefaultContext());
        $command = $this->createUpdateCommand('initial', 'initial');

        $postWriteEventDispatched = false;

        $this->dispatcher->addListener(PostWriteValidationEvent::class, static function (PostWriteValidationEvent $event) use (&$postWriteEventDispatched): void {
            $postWriteEventDispatched = true;

            static::assertCount(0, $event->getExceptions()->getExceptions());
        });

        $gateway->execute([$command], $context);

        static::assertTrue($postWriteEventDispatched);
    }

    public function testRetryClearsExceptionsFromFailedAttempt(): void
    {
        $recordChangedException = new DriverException(
            new PdoException('Record has changed since last read', 'HY000', 1020),
            null,
        );
        $attempts = 0;
        $transactionAttempts = 0;
        $transactionNestingLevel = 0;

        $connection = $this->createMock(Connection::class);
        $connection->method('getTransactionNestingLevel')
            ->willReturnCallback(static function () use (&$transactionNestingLevel): int {
                return $transactionNestingLevel;
            });
        $connection->expects($this->exactly(2))
            ->method('transactional')
            ->willReturnCallback(static function (\Closure $closure) use ($connection, &$transactionAttempts, &$transactionNestingLevel): mixed {
                ++$transactionAttempts;
                $transactionNestingLevel = 1;

                try {
                    return $closure($connection);
                } finally {
                    $transactionNestingLevel = 0;
                }
            });
        $connection->expects($this->exactly(2))
            ->method('update')
            ->willReturnCallback(static function () use (&$attempts, $recordChangedException): int {
                ++$attempts;

                if ($attempts === 1) {
                    throw $recordChangedException;
                }

                return 1;
            });

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('immutable_test');
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('getByEntityName')->willReturn($definition);

        $successCallbacks = 0;
        $errorCallbacks = 0;
        $this->dispatcher->addListener(EntityWriteEvent::class, static function (EntityWriteEvent $event) use (&$successCallbacks, &$errorCallbacks): void {
            $event->addSuccess(static function () use (&$successCallbacks): void {
                ++$successCallbacks;
            });
            $event->addError(static function () use (&$errorCallbacks): void {
                ++$errorCallbacks;
            });
        });

        $gateway = new EntityWriteGateway(
            100,
            $connection,
            $this->dispatcher,
            static::createStub(ExceptionHandlerRegistry::class),
            $definitionRegistry,
        );
        $context = WriteContext::createFromContext(Context::createDefaultContext());

        $gateway->execute([$this->createUpdateCommand('initial', 'initial')], $context);

        static::assertSame(2, $attempts);
        static::assertSame(2, $transactionAttempts);
        static::assertSame([], $context->getExceptions()->getExceptions());
        static::assertSame(1, $successCallbacks);
        static::assertSame(0, $errorCallbacks);
    }

    public function testThrowingErrorCompensationPreservesTheWriteFailureWithoutRetrying(): void
    {
        $connection = $this->createTransactionConnection();
        $gateway = $this->createGateway($connection);
        $failure = new DriverException(new PdoException('Record has changed since last read', 'HY000', 1020), null);
        $attempts = 0;
        $compensations = 0;
        $this->dispatcher->addListener(EntityWriteEvent::class, static function (EntityWriteEvent $event) use (&$compensations): void {
            $event->addError(static function () use (&$compensations): never {
                ++$compensations;

                throw new \RuntimeException('compensation failed');
            });
        });
        $this->dispatcher->addListener(PreWriteValidationEvent::class, static function () use ($failure): never {
            throw $failure;
        });

        $this->expectExceptionObject($failure);

        try {
            RetryableWriteTransaction::retryable($connection, static function () use ($gateway, &$attempts): void {
                ++$attempts;
                $gateway->execute([], WriteContext::createFromContext(Context::createDefaultContext()));
            });
        } catch (\Throwable $exception) {
            static::assertSame($failure, $exception);

            throw $exception;
        } finally {
            static::assertSame(1, $attempts);
            static::assertSame(1, $compensations);
        }
    }

    public function testThrowingExceptionListenerStillCompensatesAndPreservesTheWriteFailure(): void
    {
        $connection = $this->createTransactionConnection();
        $gateway = $this->createGateway($connection);
        $failure = new DriverException(new PdoException('Record has changed since last read', 'HY000', 1020), null);
        $attempts = 0;
        $compensations = 0;
        $this->dispatcher->addListener(EntityWriteEvent::class, static function (EntityWriteEvent $event) use (&$compensations): void {
            $event->addError(static function () use (&$compensations): void {
                ++$compensations;
            });
        });
        $this->dispatcher->addListener(PreWriteValidationEvent::class, static function () use ($failure): never {
            throw $failure;
        });
        $this->dispatcher->addListener(WriteCommandExceptionEvent::class, static function (): never {
            throw new \RuntimeException('exception notification failed');
        });

        $this->expectExceptionObject($failure);

        try {
            RetryableWriteTransaction::retryable($connection, static function () use ($gateway, &$attempts): void {
                ++$attempts;
                $gateway->execute([], WriteContext::createFromContext(Context::createDefaultContext()));
            });
        } catch (\Throwable $exception) {
            static::assertSame($failure, $exception);

            throw $exception;
        } finally {
            static::assertSame(1, $attempts);
            static::assertSame(1, $compensations);
        }
    }

    public function testPreWriteDispatchFailureRunsAlreadyRegisteredCompensation(): void
    {
        $gateway = $this->createGateway();
        $failure = new \RuntimeException('pre-write listener failed');
        $commands = [$this->createUpdateCommand('initial', 'initial')];
        $context = WriteContext::createFromContext(Context::createDefaultContext());
        $compensations = 0;
        $notifications = 0;
        $this->dispatcher->addListener(EntityWriteEvent::class, static function (EntityWriteEvent $event) use (&$compensations, $failure): never {
            $event->addError(static function () use (&$compensations): void {
                ++$compensations;
            });

            throw $failure;
        });
        $this->dispatcher->addListener(WriteCommandExceptionEvent::class, static function (WriteCommandExceptionEvent $event) use ($failure, $commands, $context, &$notifications): void {
            ++$notifications;
            static::assertSame($failure, $event->getException());
            static::assertSame($commands, $event->getCommands());
            static::assertSame($context->getContext(), $event->getContext());
        });

        $this->expectExceptionObject($failure);

        try {
            $gateway->execute($commands, $context);
        } finally {
            static::assertSame(1, $compensations);
            static::assertSame(1, $notifications);
        }
    }

    public function testSuccessfulWriteCallbacksPreventOuterRetry(): void
    {
        $connection = $this->createTransactionConnection();
        $gateway = $this->createGateway($connection);
        $failure = new DriverException(new PdoException('Record has changed since last read', 'HY000', 1020), null);
        $attempts = 0;
        $successes = 0;
        $this->dispatcher->addListener(EntityWriteEvent::class, static function (EntityWriteEvent $event) use (&$successes): void {
            $event->addSuccess(static function () use (&$successes): void {
                ++$successes;
            });
        });

        $this->expectExceptionObject($failure);

        try {
            RetryableWriteTransaction::retryable($connection, static function () use ($gateway, $failure, &$attempts): never {
                ++$attempts;
                $gateway->execute([], WriteContext::createFromContext(Context::createDefaultContext()));

                throw $failure;
            });
        } finally {
            static::assertSame(1, $attempts);
            static::assertSame(1, $successes);
        }
    }

    public function testStandaloneDeleteRetriesAfterSuccessfulCompensation(): void
    {
        $connection = $this->createTransactionConnection();
        $gateway = $this->createGateway($connection);
        $failure = new DeadlockException(new PdoException('Deadlock found when trying to get lock', '40001', 1213), null);
        $attempts = 0;
        $connection->method('delete')->willReturnCallback(static function () use ($failure, &$attempts): int {
            if (++$attempts === 1) {
                throw $failure;
            }

            return 1;
        });
        $compensations = 0;
        $successes = 0;
        $this->dispatcher->addListener(EntityDeleteEvent::class, static function (EntityDeleteEvent $event) use (&$compensations, &$successes): void {
            $event->addError(static function () use (&$compensations): void {
                ++$compensations;
            });
            $event->addSuccess(static function () use (&$successes): void {
                ++$successes;
            });
        });

        $gateway->execute([$this->createDeleteCommand()], WriteContext::createFromContext(Context::createDefaultContext()));

        static::assertSame(2, $attempts);
        static::assertSame(1, $compensations);
        static::assertSame(1, $successes);
    }

    #[DataProvider('transactionOwnershipProvider')]
    public function testSuccessfulDeleteCallbacksPreventRetryAfterPostWriteFailure(bool $hasOuterTransaction): void
    {
        $connection = $this->createTransactionConnection();
        $gateway = $this->createGateway($connection);
        $failure = new DriverException(new PdoException('Record has changed since last read', 'HY000', 1020), null);
        $command = $this->createDeleteCommand();
        $attempts = 0;
        $successes = 0;
        $this->dispatcher->addListener(EntityDeleteEvent::class, static function (EntityDeleteEvent $event) use (&$attempts, &$successes): void {
            ++$attempts;
            $event->addSuccess(static function () use (&$successes): void {
                ++$successes;
            });
        });
        $this->dispatcher->addListener(PostWriteValidationEvent::class, static function () use ($failure): never {
            throw $failure;
        });

        $this->expectExceptionObject($failure);

        try {
            $write = static function () use ($gateway, $command): void {
                $gateway->execute([$command], WriteContext::createFromContext(Context::createDefaultContext()));
            };
            if ($hasOuterTransaction) {
                RetryableWriteTransaction::retryable($connection, $write);
            } else {
                $write();
            }
        } finally {
            static::assertSame(1, $attempts);
            static::assertSame(1, $successes);
        }
    }

    #[DataProvider('transactionOwnershipProvider')]
    public function testThrowingDeleteCompensationPreservesTheDeleteFailureWithoutRetrying(bool $hasOuterTransaction): void
    {
        $connection = $this->createTransactionConnection();
        $gateway = $this->createGateway($connection);
        $failure = new DeadlockException(new PdoException('Deadlock found when trying to get lock', '40001', 1213), null);
        $connection->method('delete')->willThrowException($failure);
        $command = $this->createDeleteCommand();
        $attempts = 0;
        $compensations = 0;
        $this->dispatcher->addListener(EntityDeleteEvent::class, static function (EntityDeleteEvent $event) use (&$attempts, &$compensations): void {
            ++$attempts;
            $event->addError(static function () use (&$compensations): never {
                ++$compensations;

                throw new \RuntimeException('delete compensation failed');
            });
        });

        $this->expectExceptionObject($failure);

        try {
            $write = static function () use ($gateway, $command): void {
                $gateway->execute([$command], WriteContext::createFromContext(Context::createDefaultContext()));
            };
            if ($hasOuterTransaction) {
                RetryableWriteTransaction::retryable($connection, $write);
            } else {
                $write();
            }
        } catch (\Throwable $exception) {
            static::assertSame($failure, $exception);

            throw $exception;
        } finally {
            static::assertSame(1, $attempts);
            static::assertSame(1, $compensations);
        }
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function transactionOwnershipProvider(): iterable
    {
        yield 'standalone write' => [false];
        yield 'guarded outer transaction' => [true];
    }

    private function createDeleteCommand(): DeleteCommand
    {
        $definition = new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'deleted_entity';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
                ]);
            }
        };
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));
        $primaryKey = ['id' => Uuid::randomBytes()];

        return new DeleteCommand($definition, $primaryKey, new EntityExistence('deleted_entity', $primaryKey, true, false, false, []));
    }

    private function createTransactionConnection(): Connection&Stub
    {
        $nestingLevel = 0;
        $connection = static::createStub(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturnCallback(static function () use (&$nestingLevel): int {
            return $nestingLevel;
        });
        $connection->method('transactional')->willReturnCallback(static function (\Closure $closure) use ($connection, &$nestingLevel): mixed {
            ++$nestingLevel;

            try {
                return $closure($connection);
            } finally {
                --$nestingLevel;
            }
        });

        return $connection;
    }

    private function createGateway(?Connection $connection = null): EntityWriteGateway
    {
        return new EntityWriteGateway(
            100,
            $connection ?? new FakeConnection([]),
            $this->dispatcher,
            static::createStub(ExceptionHandlerRegistry::class),
            static::createStub(DefinitionInstanceRegistry::class)
        );
    }

    private function createUpdateCommand(string $newValue, string $oldValue): UpdateCommand
    {
        $definition = new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'immutable_test';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
                    (new StringField('name', 'name'))->addFlags(new Required()),
                    (new StringField('immutable_field', 'immutableField'))->addFlags(new Immutable()),
                ]);
            }
        };

        $registry = new StaticDefinitionInstanceRegistry(
            [$definition],
            static::createStub(ValidatorInterface::class),
            $this->createGateway()
        );

        $primaryKey = ['id' => Uuid::randomBytes()];
        $state = ['id' => $primaryKey['id'], 'immutable_field' => $oldValue];
        $existence = new EntityExistence('immutable_test', $primaryKey, true, false, false, $state);

        $command = new UpdateCommand(
            $registry->getByEntityName('immutable_test'),
            ['immutable_field' => $newValue],
            $primaryKey,
            $existence,
            '/0'
        );
        $command->setImmutableFieldsChanges(['immutable_field']);
        $command->setChangeSet(new ChangeSet($state, ['immutable_field' => $newValue], false));

        return $command;
    }
}
