<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\SystemCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Maintenance\System\SystemCheck\DeploymentReadinessCheck;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeploymentReadinessCheck::class)]
class DeploymentReadinessCheckTest extends TestCase
{
    private const APP_URL = 'https://example.com';

    public function testName(): void
    {
        $check = new DeploymentReadinessCheck($this->createMock(Connection::class), self::APP_URL);

        static::assertSame('DeploymentReadiness', $check->name());
    }

    public function testCategory(): void
    {
        $check = new DeploymentReadinessCheck($this->createMock(Connection::class), self::APP_URL);

        static::assertSame(Category::SYSTEM, $check->category());
    }

    public function testAllowedExecutionContexts(): void
    {
        $check = new DeploymentReadinessCheck($this->createMock(Connection::class), self::APP_URL);

        static::assertTrue($check->allowedToRunIn(SystemCheckExecutionContext::CLI));
        static::assertTrue($check->allowedToRunIn(SystemCheckExecutionContext::PRE_ROLLOUT));
        static::assertFalse($check->allowedToRunIn(SystemCheckExecutionContext::RECURRENT));
        static::assertFalse($check->allowedToRunIn(SystemCheckExecutionContext::WEB));
    }

    public function testRunReturnsOkWhenAllCheckPass(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback($this->allHealthyCallback());

        $check = new DeploymentReadinessCheck($connection, self::APP_URL);
        $result = $check->run();

        static::assertSame(Status::OK, $result->status);
        static::assertSame('System is ready for deployment', $result->message);
        static::assertTrue($result->healthy);
        static::assertSame([], $result->extra);
    }

    public function testRunReturnsFailureWhenMigrationsArePending(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(
            $this->allHealthyCallback(pendingMigrations: 3)
        );

        $result = (new DeploymentReadinessCheck($connection, self::APP_URL))->run();

        static::assertSame(Status::FAILURE, $result->status);
        static::assertFalse($result->healthy);
        static::assertContains('3 pending migration(s) not yet executed', $result->extra);
    }

    public function testRunReturnsFailureWhenNoAdminUserExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(
            $this->allHealthyCallback(adminUsers: 0)
        );

        $result = (new DeploymentReadinessCheck($connection, self::APP_URL))->run();

        static::assertSame(Status::FAILURE, $result->status);
        static::assertFalse($result->healthy);
        static::assertContains('No admin user found', $result->extra);
    }

    public function testRunReturnsFailureWhenNoSalesChannelMatchesAppUrl(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(
            $this->allHealthyCallback(salesChannelExists: 0)
        );

        $result = (new DeploymentReadinessCheck($connection, self::APP_URL))->run();

        static::assertSame(Status::FAILURE, $result->status);
        static::assertFalse($result->healthy);
        static::assertContains(
            \sprintf('No sales channel domain found matching APP_URL "%s"', self::APP_URL),
            $result->extra
        );
    }

    public function testRunReturnsFailureWhenNoScheduledTasksRegistered(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(
            $this->allHealthyCallback(scheduledTasks: 0)
        );

        $result = (new DeploymentReadinessCheck($connection, self::APP_URL))->run();

        static::assertSame(Status::FAILURE, $result->status);
        static::assertFalse($result->healthy);
        static::assertContains('No scheduled tasks registered', $result->extra);
    }

    public function testRunReturnsAllFailuresAtOnce(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(
            $this->allHealthyCallback(
                pendingMigrations: 2,
                adminUsers: 0,
                salesChannelExists: 0,
                scheduledTasks: 0
            )
        );

        $result = (new DeploymentReadinessCheck($connection, self::APP_URL))->run();

        static::assertSame(Status::FAILURE, $result->status);
        static::assertFalse($result->healthy);
        static::assertCount(4, $result->extra);
    }

    public function testAppUrlTrailingSlashIsStripped(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(
            function (string $sql, array $params = []) {
                if (str_contains($sql, 'sales_channel_domain')) {
                    static::assertSame('https://example.com', $params['url']);

                    return '1';
                }

                return '1';
            }
        );

        (new DeploymentReadinessCheck($connection, 'https://example.com/'))->run();
    }

    /**
     * Returns a fetchOne callback where every check is healthy by default.
     * Individual counts can be overridden to simulate failures.
     */
    private function allHealthyCallback(
        int $pendingMigrations = 0,
        int $adminUsers = 1,
        int $salesChannelExists = 1,
        int $scheduledTasks = 5,
    ): \Closure {
        return function (string $sql) use ($pendingMigrations, $adminUsers, $salesChannelExists, $scheduledTasks): string {
            return match (true) {
                str_contains($sql, 'migration') => (string) $pendingMigrations,
                str_contains($sql, 'user') => (string) $adminUsers,
                str_contains($sql, 'sales_channel_domain') => (string) $salesChannelExists,
                str_contains($sql, 'scheduled_task') => (string) $scheduledTasks,
                default => '0',
            };
        };
    }
}
