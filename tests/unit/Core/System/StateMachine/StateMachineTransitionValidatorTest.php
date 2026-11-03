<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\StateMachine;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Shopware\Core\System\StateMachine\StateMachineTransitionValidator;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Will be removed together with StateMachineTransitionValidator.
 */
#[Package('checkout')]
#[CoversClass(StateMachineTransitionValidator::class)]
class StateMachineTransitionValidatorTest extends TestCase
{
    private EntityDefinition $definition;

    protected function setUp(): void
    {
        $definitionRegistry = new StaticDefinitionInstanceRegistry(
            [StateMachineDefinition::class, StateMachineStateDefinition::class, StateMachineTransitionDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $this->definition = $definitionRegistry->get(StateMachineTransitionDefinition::class);
    }

    public function testNoSubscribersInMajorMode(): void
    {
        static::assertSame([], StateMachineTransitionValidator::getSubscribedEvents());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testDirectInvocationThrowsInMajorMode(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');
        $validator = new StateMachineTransitionValidator($connection);
        $this->expectException(FeatureException::class);
        $validator->preValidate($this->createEvent([]));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInsertWithoutConflictIsAccepted(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(false);
        $validator = new StateMachineTransitionValidator($connection);

        $event = $this->createEvent([
            $this->createInsertCommand(Uuid::randomBytes(), 'authorize'),
        ]);

        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testUpdateChangingActionNameIntoConflictIsDeprecated(): void
    {
        $id = Uuid::randomBytes();
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'action_name' => 'reopen',
            'state_machine_id' => Uuid::randomBytes(),
            'from_state_id' => Uuid::randomBytes(),
        ]);
        $connection->method('fetchOne')->willReturn('1');
        $validator = new StateMachineTransitionValidator($connection);

        $event = $this->createEvent([
            new UpdateCommand(
                $this->definition,
                ['action_name' => 'authorize'],
                ['id' => $id],
                static::createStub(EntityExistence::class),
                '/0/'
            ),
        ]);

        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testUpdateNotTouchingActionOrSourceStateIsIgnored(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('1');
        $validator = new StateMachineTransitionValidator($connection);

        $event = $this->createEvent([
            new UpdateCommand(
                $this->definition,
                ['to_state_id' => Uuid::randomBytes()],
                ['id' => Uuid::randomBytes()],
                static::createStub(EntityExistence::class),
                '/0/'
            ),
        ]);

        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConflictingInsertIsOnlyDeprecatedWithoutMajorFlag(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('1');
        $validator = new StateMachineTransitionValidator($connection);

        $event = $this->createEvent([
            $this->createInsertCommand(Uuid::randomBytes(), 'authorize'),
        ]);

        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * @param list<InsertCommand|UpdateCommand> $commands
     */
    private function createEvent(array $commands): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            $commands
        );
    }

    private function createInsertCommand(
        string $id,
        string $actionName,
        ?string $stateMachineId = null,
        ?string $fromStateId = null
    ): InsertCommand {
        return new InsertCommand(
            $this->definition,
            [
                'id' => $id,
                'action_name' => $actionName,
                'state_machine_id' => $stateMachineId ?? Uuid::randomBytes(),
                'from_state_id' => $fromStateId ?? Uuid::randomBytes(),
                'to_state_id' => Uuid::randomBytes(),
            ],
            ['id' => $id],
            static::createStub(EntityExistence::class),
            '/0/'
        );
    }
}
