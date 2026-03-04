<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\SystemCheck\SystemChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: SystemCheckTask::class)]
#[Package('framework')]
final class SystemCheckTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly SystemChecker $systemChecker,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $results = $this->systemChecker->check(SystemCheckExecutionContext::RECURRENT);

        foreach ($results as $result) {
            if ($result->healthy === false) {
                $this->exceptionLogger->error(
                    'System check "{name}" is unhealthy: {message}',
                    [
                        'name' => $result->name,
                        'status' => $result->status->name,
                        'message' => $result->message,
                        'extra' => $result->extra,
                    ]
                );
            }
        }
    }
}
