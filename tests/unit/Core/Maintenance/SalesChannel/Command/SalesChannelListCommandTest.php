<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\SalesChannel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelListCommand;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(SalesChannelListCommand::class)]
class SalesChannelListCommandTest extends TestCase
{
    public function testNoValidationErrors(): void
    {
        $id = Uuid::randomHex();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setUniqueIdentifier($id);
        $salesChannel->setId($id);
        $salesChannel->setActive(true);
        $salesChannel->setMaintenance(false);

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticEntityRepository([new SalesChannelCollection([$salesChannel])], new SalesChannelDefinition());

        $command = new SalesChannelListCommand($salesChannelRepository);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        static::assertSame(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:list\" returned errors:\n" . $commandTester->getDisplay()
        );
        $output = '+----------------------------------+------+--------+-------------+------------------+-----------+------------------+------------+---------+
| id                               | Name | Active | Maintenance | Default Language | Languages | Default Currency | Currencies | Domains |
+----------------------------------+------+--------+-------------+------------------+-----------+------------------+------------+---------+
| %s | n/a  | active | off         | n/a              |           | n/a              |            |         |
+----------------------------------+------+--------+-------------+------------------+-----------+------------------+------------+---------+
';
        static::assertSame(\sprintf($output, $id), $commandTester->getDisplay());
    }
}
