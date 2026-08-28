<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1773647849AlignFlowNameWithEventName;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1773647849AlignFlowNameWithEventName::class)]
class Migration1773647849AlignFlowNameWithEventNameTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1773647849, (new Migration1773647849AlignFlowNameWithEventName())->getCreationTimestamp());
    }

    public function testAlignFlowNameWithEventName(): void
    {
        $flowIds = [];

        foreach (Migration1773647849AlignFlowNameWithEventName::FLOW_TRANSLATION_MAPPING as $eventName => $data) {
            $flowId = $this->connection->fetchOne(
                'SELECT id FROM flow WHERE event_name = :eventName',
                ['eventName' => $eventName]
            );

            if (!$flowId) {
                continue;
            }

            $this->connection->update(
                'flow',
                ['name' => $data['old']],
                ['id' => $flowId]
            );

            $flowIds[$eventName] = $flowId;
        }

        static::assertCount(
            \count(Migration1773647849AlignFlowNameWithEventName::FLOW_TRANSLATION_MAPPING),
            $flowIds
        );

        $migration = new Migration1773647849AlignFlowNameWithEventName();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (Migration1773647849AlignFlowNameWithEventName::FLOW_TRANSLATION_MAPPING as $eventName => $data) {
            static::assertArrayHasKey($eventName, $flowIds);

            $name = $this->connection->fetchOne(
                'SELECT name FROM flow WHERE id = :id',
                ['id' => $flowIds[$eventName]]
            );

            static::assertSame($data['new'], $name);
        }
    }
}
