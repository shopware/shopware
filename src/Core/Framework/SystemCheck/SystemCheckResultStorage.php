<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;

/**
 * @internal
 */
#[Package('framework')]
class SystemCheckResultStorage
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<Result> $results
     */
    public function save(array $results, SystemCheckExecutionContext $context): void
    {
        $checkedAt = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $queue = new MultiInsertQueryQueue($this->connection, useReplace: true);

        try {
            foreach ($results as $result) {
                $queue->addInsert('system_check_result', [
                    'name' => $result->name,
                    'status' => $result->status->name,
                    'healthy' => $result->healthy === null ? null : (int) $result->healthy,
                    'message' => $result->message,
                    'extra' => json_encode($result->extra, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION),
                    'execution_context' => $context->value,
                    'checked_at' => $checkedAt,
                ]);
            }

            $queue->execute();
        } catch (\Throwable) {
        }
    }
}
