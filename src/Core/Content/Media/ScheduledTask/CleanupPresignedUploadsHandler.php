<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\Upload\PresignedUrlGeneratorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('discovery')]
#[AsMessageHandler(handles: CleanupPresignedUploadsTask::class)]
final class CleanupPresignedUploadsHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        protected EntityRepository $scheduledTaskRepository,
        protected readonly LoggerInterface $logger,
        private readonly PresignedUrlGeneratorInterface $presignedUrlGenerator,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        if (!$this->presignedUrlGenerator->isSupported()) {
            return;
        }

        $this->presignedUrlGenerator->deleteAbandonedStagingUploads();
    }
}
