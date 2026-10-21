<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\EmptyEntityExistence;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WriteCommandQueue::class)]
class WriteCommandQueueTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new StaticDefinitionInstanceRegistry(
            [CountryDefinition::class, CountryStateDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testEmptyQueueYieldsNoCommands(): void
    {
        static::assertSame([], (new WriteCommandQueue())->getCommandsInOrder($this->registry));
    }

    public function testReturnsAllCommandsOfOneEntityInInsertionOrder(): void
    {
        $queue = new WriteCommandQueue();
        $first = $this->countryInsert($queue);
        $second = $this->countryInsert($queue);

        static::assertSame([$first, $second], $queue->getCommandsInOrder($this->registry));
    }

    public function testOrdersReferencedEntityBeforeDependentEntity(): void
    {
        $queue = new WriteCommandQueue();
        $countryId = Uuid::randomBytes();

        $state = $this->stateInsert($queue, $countryId);
        $country = $this->countryInsert($queue, $countryId);

        static::assertSame([$country, $state], $queue->getCommandsInOrder($this->registry));
    }

    public function testKeepsCommandsOfOtherEntitiesWhileOneEntityIsDrained(): void
    {
        $queue = new WriteCommandQueue();
        $countryId = Uuid::randomBytes();

        $stateA = $this->stateInsert($queue, $countryId);
        $stateB = $this->stateInsert($queue, $countryId);
        $country = $this->countryInsert($queue, $countryId);

        static::assertSame([$country, $stateA, $stateB], $queue->getCommandsInOrder($this->registry));
    }

    public function testKeepsDelayedCommandQueuedWhileSiblingOfSameEntityIsTaken(): void
    {
        $queue = new WriteCommandQueue();
        $countryId = Uuid::randomBytes();

        $independentState = $this->stateInsert($queue, Uuid::randomBytes());
        $dependentState = $this->stateInsert($queue, $countryId);
        $country = $this->countryInsert($queue, $countryId);

        static::assertSame([$independentState, $country, $dependentState], $queue->getCommandsInOrder($this->registry));
    }

    private function countryInsert(WriteCommandQueue $queue, ?string $id = null): WriteCommand
    {
        $id ??= Uuid::randomBytes();
        $command = new InsertCommand(
            definition: $this->registry->get(CountryDefinition::class),
            payload: ['id' => $id],
            primaryKey: ['id' => $id],
            existence: new EmptyEntityExistence(),
            path: '/' . Uuid::randomHex()
        );
        $queue->add($command->getEntityName(), WriteCommandQueue::hashedPrimary($this->registry, $command), $command);

        return $command;
    }

    private function stateInsert(WriteCommandQueue $queue, string $countryId): WriteCommand
    {
        $id = Uuid::randomBytes();
        $command = new InsertCommand(
            definition: $this->registry->get(CountryStateDefinition::class),
            payload: ['id' => $id, 'country_id' => $countryId],
            primaryKey: ['id' => $id],
            existence: new EmptyEntityExistence(),
            path: '/' . Uuid::randomHex()
        );
        $queue->add($command->getEntityName(), WriteCommandQueue::hashedPrimary($this->registry, $command), $command);

        return $command;
    }
}
