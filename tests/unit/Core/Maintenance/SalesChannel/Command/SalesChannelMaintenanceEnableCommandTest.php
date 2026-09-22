<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\SalesChannel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelMaintenanceEnableCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelMaintenanceEnableCommand::class)]
class SalesChannelMaintenanceEnableCommandTest extends TestCase
{
    public function testNoIdsReturnsWithoutUpdating(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('searchIds');
        $repository->expects($this->never())->method('update');

        $tester = new CommandTester(new SalesChannelMaintenanceEnableCommand($repository));

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertSame(
            'No sales channels were updated. Provide id(s) or run with --all option.',
            $tester->getDisplay(),
        );
    }

    public function testIdsUpdateMaintenanceMode(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('searchIds')
            ->with(
                static::callback(static function (Criteria $criteria): bool {
                    return $criteria->getIds() === ['sales-channel-id'];
                }),
                static::isInstanceOf(Context::class),
            )
            ->willReturn(IdSearchResult::fromIds(['sales-channel-id'], new Criteria(), Context::createCLIContext()));
        $repository->expects($this->once())
            ->method('update')
            ->with(
                [['id' => 'sales-channel-id', 'maintenance' => true]],
                static::isInstanceOf(Context::class),
            );

        $tester = new CommandTester(new SalesChannelMaintenanceEnableCommand($repository));

        static::assertSame(Command::SUCCESS, $tester->execute(['ids' => ['sales-channel-id']]));
        static::assertSame('Updated maintenance mode for 1 sales channel(s)', $tester->getDisplay());
    }

    public function testAllIdsUpdateAllSalesChannels(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('searchIds')
            ->with(
                static::callback(static function (Criteria $criteria): bool {
                    return $criteria->getIds() === [];
                }),
                static::isInstanceOf(Context::class),
            )
            ->willReturn(IdSearchResult::fromIds(['first-id', 'second-id'], new Criteria(), Context::createCLIContext()));
        $repository->expects($this->once())
            ->method('update')
            ->with(
                [
                    ['id' => 'first-id', 'maintenance' => true],
                    ['id' => 'second-id', 'maintenance' => true],
                ],
                static::isInstanceOf(Context::class),
            );

        $tester = new CommandTester(new SalesChannelMaintenanceEnableCommand($repository));

        static::assertSame(Command::SUCCESS, $tester->execute(['--all' => true]));
        static::assertSame('Updated maintenance mode for 2 sales channel(s)', $tester->getDisplay());
    }

    public function testNoMatchingSalesChannelsReturnsWithoutUpdating(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('searchIds')->willReturn(
            IdSearchResult::fromIds([], new Criteria(), Context::createCLIContext()),
        );
        $repository->expects($this->never())->method('update');

        $tester = new CommandTester(new SalesChannelMaintenanceEnableCommand($repository));

        static::assertSame(Command::SUCCESS, $tester->execute(['ids' => ['unknown-id']]));
        static::assertSame('No sales channels were updated', $tester->getDisplay());
    }
}
