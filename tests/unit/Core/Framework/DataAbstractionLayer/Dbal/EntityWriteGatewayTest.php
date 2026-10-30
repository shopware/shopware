<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('immutableFieldChanges')]
    public function testImmutableFieldChangeThrowsViolation(string|int|bool|null $newValue, string|int|bool $oldValue): void
    {
        $gateway = $this->createGateway();
        $context = WriteContext::createFromContext(Context::createDefaultContext());
        $command = $this->createUpdateCommand($newValue, $oldValue);

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
                $oldValue,
                'immutable_field',
                $newValue
            )
        );

        $exception->add(new WriteConstraintViolationException($violationList));
        static::expectExceptionObject($exception);

        $gateway->execute([$command], $context);
    }

    /**
     * @return iterable<string, array{string|int|bool|null, string|int|bool}>
     */
    public static function immutableFieldChanges(): iterable
    {
        yield 'changed string' => ['updated', 'initial'];
        yield 'empty string' => ['', 'initial'];
        yield 'zero' => [0, 1];
        yield 'false' => [false, true];
        yield 'true' => [true, false];
        yield 'null' => [null, 'initial'];
    }

    #[DataProvider('unchangedImmutableFields')]
    public function testImmutableFieldSameValueIsIgnored(string|int|bool|null $value): void
    {
        $gateway = $this->createGateway();
        $context = WriteContext::createFromContext(Context::createDefaultContext());
        $command = $this->createUpdateCommand($value, $value);

        $postWriteEventDispatched = false;

        $this->dispatcher->addListener(PostWriteValidationEvent::class, static function (PostWriteValidationEvent $event) use (&$postWriteEventDispatched): void {
            $postWriteEventDispatched = true;

            static::assertCount(0, $event->getExceptions()->getExceptions());
        });

        $gateway->execute([$command], $context);

        static::assertTrue($postWriteEventDispatched);
    }

    /**
     * @return iterable<string, array{string|int|bool|null}>
     */
    public static function unchangedImmutableFields(): iterable
    {
        yield 'string' => ['initial'];
        yield 'false' => [false];
        yield 'true' => [true];
        yield 'zero' => [0];
        yield 'null' => [null];
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

    private function createUpdateCommand(string|int|bool|null $newValue, string|int|bool|null $oldValue): UpdateCommand
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
