<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListVersions;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Clock\NativeClock;

/**
 * Runs the atomic increment against the real database.
 *
 * @internal
 */
#[Package('framework')]
class McpListVersionsTest extends TestCase
{
    use IntegrationTestBehaviour;

    private McpListVersions $versions;

    protected function setUp(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM `mcp_list_version`');
        $this->versions = new McpListVersions(static::getContainer()->get(Connection::class), new NativeClock());
    }

    public function testAllListsStartAtZero(): void
    {
        static::assertSame(['tools' => 0, 'resources' => 0, 'prompts' => 0], $this->versions->current());
    }

    public function testBumpIncrementsOnlyTheChangedLists(): void
    {
        $this->versions->bump(new McpListChangedNotificationSet(tools: true, resources: false, prompts: true));
        $this->versions->bump(new McpListChangedNotificationSet(tools: true, resources: false, prompts: false));

        static::assertSame(['tools' => 2, 'resources' => 0, 'prompts' => 1], $this->versions->current());
    }

    public function testBumpWithoutChangesWritesNothing(): void
    {
        $this->versions->bump(McpListChangedNotificationSet::none());

        static::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM `mcp_list_version`'));
    }
}
