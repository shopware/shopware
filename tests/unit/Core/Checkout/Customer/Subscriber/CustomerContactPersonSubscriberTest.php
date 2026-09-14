<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerContactPersonSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerContactPersonSubscriber::class)]
class CustomerContactPersonSubscriberTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [CustomerDefinition::class, CustomerAddressDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [PreWriteValidationEvent::class => 'validate'],
            CustomerContactPersonSubscriber::getSubscribedEvents()
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('insertProvider')]
    public function testInsert(string $entity, array $payload, bool $valid): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociativeIndexed');

        $event = $this->event($this->insert($entity, $payload));

        (new CustomerContactPersonSubscriber($connection))->validate($event);

        $this->assertOutcome($event, $valid);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, bool}>
     */
    public static function insertProvider(): iterable
    {
        $customer = CustomerDefinition::ENTITY_NAME;
        $address = CustomerAddressDefinition::ENTITY_NAME;

        yield 'private customer with a contact person' => [$customer, ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'account_type' => 'private'], true];
        yield 'private customer with a last name only' => [$customer, ['first_name' => '', 'last_name' => 'Lovelace', 'account_type' => 'private'], true];
        yield 'private customer without a contact person' => [$customer, ['first_name' => '', 'last_name' => '', 'account_type' => 'private', 'company' => 'Acme GmbH'], false];
        yield 'customer without an account type counts as private' => [$customer, ['first_name' => '', 'last_name' => '', 'company' => 'Acme GmbH'], false];
        yield 'commercial customer with a company' => [$customer, ['first_name' => '', 'last_name' => '', 'account_type' => 'business', 'company' => 'Acme GmbH'], true];
        yield 'commercial customer with a blank company' => [$customer, ['first_name' => ' ', 'last_name' => '', 'account_type' => 'business', 'company' => '  '], false];
        yield 'commercial customer without a company' => [$customer, ['first_name' => '', 'last_name' => '', 'account_type' => 'business'], false];
        yield 'address with a contact person' => [$address, ['first_name' => 'Ada', 'last_name' => 'Lovelace'], true];
        yield 'address with a company only' => [$address, ['first_name' => '', 'last_name' => '', 'company' => 'Acme GmbH'], true];
        yield 'address naming nobody' => [$address, ['first_name' => '', 'last_name' => '', 'company' => null], false];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string|null> $stored
     */
    #[DataProvider('updateProvider')]
    public function testUpdateMergesTheStoredRow(array $payload, array $stored, bool $valid): void
    {
        $id = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociativeIndexed')
            ->willReturn([$id => $stored]);

        $event = $this->event($this->update(CustomerDefinition::ENTITY_NAME, $id, $payload));

        (new CustomerContactPersonSubscriber($connection))->validate($event);

        $this->assertOutcome($event, $valid);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, string|null>, bool}>
     */
    public static function updateProvider(): iterable
    {
        $business = ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'company' => 'Acme GmbH', 'account_type' => 'business'];
        $private = ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'company' => null, 'account_type' => 'private'];

        yield 'clearing the names of a commercial account with a company' => [['first_name' => '', 'last_name' => ''], $business, true];
        yield 'clearing the names of a private account' => [['first_name' => '', 'last_name' => ''], $private, false];
        yield 'clearing the company of a nameless commercial account' => [['company' => null], ['first_name' => '', 'last_name' => '', 'company' => 'Acme GmbH', 'account_type' => 'business'], false];
        yield 'switching a nameless commercial account to private' => [['account_type' => 'private'], ['first_name' => '', 'last_name' => '', 'company' => 'Acme GmbH', 'account_type' => 'business'], false];
        yield 'clearing one name keeps the other' => [['first_name' => ''], $private, true];
    }

    public function testUpdateWithoutANameFieldIsNotChecked(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociativeIndexed');

        $event = $this->event($this->update(CustomerDefinition::ENTITY_NAME, Uuid::randomHex(), ['email' => 'ada@example.com']));

        (new CustomerContactPersonSubscriber($connection))->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testViolationNamesTheFirstNameOfTheCommand(): void
    {
        $event = $this->event($this->insert(CustomerDefinition::ENTITY_NAME, ['first_name' => '', 'last_name' => '', 'account_type' => 'private'], '/3'));

        (new CustomerContactPersonSubscriber(static::createStub(Connection::class)))->validate($event);

        $exception = $event->getExceptions()->getExceptions()[0] ?? null;
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);

        $violation = $exception->getViolations()->get(0);
        static::assertSame('/3/firstName', $violation->getPropertyPath());
        static::assertSame(NotBlank::IS_BLANK_ERROR, $violation->getCode());
        static::assertSame(CustomerContactPersonSubscriber::MESSAGE, $violation->getMessage());
    }

    private function assertOutcome(PreWriteValidationEvent $event, bool $valid): void
    {
        $exceptions = $event->getExceptions()->getExceptions();

        if ($valid) {
            static::assertCount(0, $exceptions);

            return;
        }

        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);
    }

    private function event(WriteCommand ...$commands): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), array_values($commands));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insert(string $entity, array $payload, string $path = '/0'): InsertCommand
    {
        $id = Uuid::randomBytes();

        return new InsertCommand(
            $this->definition($entity),
            $payload + ['id' => $id],
            ['id' => $id],
            new EntityExistence($entity, ['id' => $id], false, false, false, []),
            $path
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function update(string $entity, string $id, array $payload): UpdateCommand
    {
        $bytes = Uuid::fromHexToBytes($id);

        return new UpdateCommand(
            $this->definition($entity),
            $payload,
            ['id' => $bytes],
            new EntityExistence($entity, ['id' => $bytes], true, false, false, []),
            '/0'
        );
    }

    private function definition(string $entity): EntityDefinition
    {
        return $this->registry->getByEntityName($entity);
    }
}
