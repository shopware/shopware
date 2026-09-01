<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PdoException;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
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

    private function createGateway(): EntityWriteGateway
    {
        return new EntityWriteGateway(
            100,
            new FakeConnection([]),
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
