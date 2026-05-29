<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\SystemCheck;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;

/**
 * @internal
 */
#[Package('framework')]
class DeploymentReadinessCheck extends BaseCheck
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $appUrl,
    ) {
    }

    public function name(): string
    {
        return 'DeploymentReadiness';
    }

    public function category(): Category
    {
        return Category::SYSTEM;
    }

    public function run(): Result
    {
        $failures = [];

        // Check 1: No pending migrations
        $pendingMigrations = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `migration` WHERE `update` IS NULL'
        );
        if ($pendingMigrations > 0) {
            $failures[] = \sprintf('%d pending migration(s) not yet executed', $pendingMigrations);
        }

        // Check 2: At least one admin user exists
        $adminUsers = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `user` WHERE `admin` = 1'
        );
        if ($adminUsers === 0) {
            $failures[] = 'No admin user found';
        }

        // Check 3: Sales channel domain matching APP_URL exists
        $salesChannelExists = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `sales_channel_domain` WHERE `url` = :url',
            ['url' => rtrim($this->appUrl, '/')]
        );
        if ($salesChannelExists === 0) {
            $failures[] = \sprintf('No sales channel domain found matching APP_URL "%s"', $this->appUrl);
        }

        // Check 4: Scheduled tasks are registered
        $scheduledTasks = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `scheduled_task`'
        );
        if ($scheduledTasks === 0) {
            $failures[] = 'No scheduled tasks registered';
        }

        $healthy = $failures === [];
        $status = $healthy ? Status::OK : Status::FAILURE;

        return new Result(
            $this->name(),
            $status,
            $healthy ? 'System is ready for deployment' : 'System is not ready for deployment',
            $healthy,
            $failures,
        );
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return [SystemCheckExecutionContext::CLI, SystemCheckExecutionContext::PRE_ROLLOUT];
    }
}
