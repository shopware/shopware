<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

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
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
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

    private function createGateway(): EntityWriteGateway
    {
        return new EntityWriteGateway(
            100,
            new FakeConnection([]),
            $this->dispatcher,
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
        $command->setImmutableFieldsChanges(['immutable_field']);
        $command->setChangeSet(new ChangeSet($state, ['immutable_field' => $newValue], false));

        return $command;
    }
}
