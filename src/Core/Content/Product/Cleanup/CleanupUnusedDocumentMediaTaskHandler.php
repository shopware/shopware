<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cleanup;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Files uploaded in the last day stay, because their document may not be saved yet.
 *
 * @internal
 */
#[Package('inventory')]
#[AsMessageHandler(handles: CleanupUnusedDocumentMediaTask::class)]
final class CleanupUnusedDocumentMediaTaskHandler extends ScheduledTaskHandler
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
            gracePeriodDays: 1,
            folderEntity: ProductDocumentDefinition::ENTITY_NAME,
        );
    }
}
