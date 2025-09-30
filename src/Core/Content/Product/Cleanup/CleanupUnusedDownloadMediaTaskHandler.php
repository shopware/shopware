<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cleanup;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('inventory')]
#[AsMessageHandler(handles: CleanupUnusedDownloadMediaTask::class)]
final class CleanupUnusedDownloadMediaTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly UnusedMediaPurger $unusedMediaPurger
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $this->unusedMediaPurger->deleteNotUsedMedia(
            null,
            null,
            null,
            ProductDownloadDefinition::ENTITY_NAME
        );
    }
}
