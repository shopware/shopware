<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallengeStore;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler(handles: PruneMfaChallengesTask::class)]
final class PruneMfaChallengesTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly MfaChallengeStore $challengeStore,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        if (!Feature::isActive('ADMIN_AUTH')) {
            return;
        }

        $this->challengeStore->deleteExpired();
    }
}
