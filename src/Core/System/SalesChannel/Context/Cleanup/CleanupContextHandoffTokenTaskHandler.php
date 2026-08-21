<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context\Cleanup;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenStore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler(handles: CleanupContextHandoffTokenTask::class)]
final class CleanupContextHandoffTokenTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly ContextHandoffTokenStore $tokenStore,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $this->tokenStore->deleteExpired();
    }
}
