<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cleanup;

use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @internal
 */
#[Package('inventory')]
final class CleanupUnusedDownloadMediaTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $repository,
        private readonly UnusedMediaPurger $unusedMediaPurger
    ) {
        parent::__construct($repository);
    }

    /**
     * @return string[]
     */
    public static function getHandledMessages(): iterable
    {
        return [CleanupUnusedDownloadMediaTask::class];
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
