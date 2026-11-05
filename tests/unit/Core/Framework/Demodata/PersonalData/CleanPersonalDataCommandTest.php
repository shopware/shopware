<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\PersonalData;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Framework\Demodata\DemodataException;
use Shopware\Core\Framework\Demodata\PersonalData\CleanPersonalDataCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CleanPersonalDataCommand::class)]
class CleanPersonalDataCommandTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
    }

    public function testRejectsMissingType(): void
    {
        $this->expectExceptionObject(DemodataException::invalidArgument('Please add the argument "type=guests" to remove guests without orders or the argument "type=carts" to remove canceled carts. Use --all to clean both.'));

        $this->getCommandTester($this->createRepository([]))->execute([]);
    }

    public function testRejectsUnknownType(): void
    {
        $this->expectExceptionObject(DemodataException::invalidArgument('Please add the argument "type=guests" to remove guests without orders or the argument "type=carts" to remove canceled carts. Use --all to clean both.'));

        $this->getCommandTester($this->createRepository([]))->execute(['type' => 'unknown']);
    }

    public function testDeletesGuestsReturnedByRepository(): void
    {
        $repository = $this->createRepository(['guest-id']);
        $this->getCommandTester($repository)->execute(['type' => 'guests', '--days' => 14]);

        static::assertSame([[['id' => 'guest-id']]], $repository->deletes);
    }

    public function testDoesNotDeleteGuestsWhenRepositoryReturnsNoIds(): void
    {
        $repository = $this->createRepository([]);
        $this->getCommandTester($repository)->execute(['type' => 'guests']);

        static::assertSame([], $repository->deletes);
    }

    public function testDeletesCartsUsingConfiguredDays(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(static::stringContains('DELETE FROM cart'), ['days' => 7])
            ->willReturn(1);

        $this->getCommandTester($this->createRepository([]), $connection)->execute(['type' => 'carts', '--days' => 7]);
    }

    public function testAllCleansGuestsAndCarts(): void
    {
        $repository = $this->createRepository(['guest-id']);
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->with(
            static::stringContains('DELETE FROM cart'),
            ['days' => 0]
        );

        $tester = $this->getCommandTester($repository, $connection);
        static::assertSame(Command::SUCCESS, $tester->execute(['--all' => true]));
        static::assertSame([[['id' => 'guest-id']]], $repository->deletes);
    }

    /**
     * @param list<string> $ids
     *
     * @return StaticEntityRepository<CustomerCollection>
     */
    private function createRepository(array $ids): StaticEntityRepository
    {
        return StaticEntityRepository::of(CustomerCollection::class, [$ids]);
    }

    /**
     * @param StaticEntityRepository<CustomerCollection> $repository
     */
    private function getCommandTester(StaticEntityRepository $repository, ?Connection $connection = null): CommandTester
    {
        return new CommandTester(new CleanPersonalDataCommand($connection ?? $this->connection, $repository, new MockClock('2024-01-15 12:00:00')));
    }
}
