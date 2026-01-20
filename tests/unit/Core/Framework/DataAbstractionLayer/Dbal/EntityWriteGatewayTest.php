<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(EntityWriteGateway::class)]
class EntityWriteGatewayTest extends TestCase
{
    public function testImmutableFieldChangeThrowsViolation(): void
    {
        $gateway = $this->createGateway();
        $context = WriteContext::createFromContext(Context::createDefaultContext());
        $command = $this->createUpdateCommand('updated', 'initial');

        static::expectException(WriteException::class);
        static::expectExceptionMessage('[immutable_field] The field "immutable_field" of "immutable_test" is immutable and cannot be updated.');

        $this->invokeValidateCommands($gateway, [$command], $context);
    }

    public function testImmutableFieldSameValueIsIgnored(): void
    {
        $gateway = $this->createGateway();
        $context = WriteContext::createFromContext(Context::createDefaultContext());
        $command = $this->createUpdateCommand('initial', 'initial');

        $this->invokeValidateCommands($gateway, [$command], $context);

        static::assertTrue(true, 'No exception should be thrown when immutable field value is unchanged.');
    }

    private function invokeValidateCommands(EntityWriteGateway $gateway, array $commands, WriteContext $context): void
    {
        $method = (new \ReflectionClass(EntityWriteGateway::class))->getMethod('validateCommands');
        $method->setAccessible(true);
        $method->invoke($gateway, $commands, $context);
    }

    private function createGateway(): EntityWriteGateway
    {
        return new EntityWriteGateway(
            100,
            $this->createMock(Connection::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(ExceptionHandlerRegistry::class),
            $this->createMock(DefinitionInstanceRegistry::class)
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
            $this->createMock(ValidatorInterface::class),
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
        $command->setImmutableChanges(['immutable_field']);
        $command->setChangeSet(new ChangeSet($state, ['immutable_field' => $newValue], false));

        return $command;
    }
}
