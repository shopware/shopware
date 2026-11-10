<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cleanup;

use League\Flysystem\FilesystemOperator;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentArchiver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('inventory')]
#[AsMessageHandler(handles: CleanupProductDocumentArchivesTask::class)]
final class CleanupProductDocumentArchivesTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly FilesystemOperator $privateFilesystem,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        $expired = $this->clock->now()->modify('-1 day')->getTimestamp();

        foreach ($this->privateFilesystem->listContents(ProductDocumentArchiver::DIRECTORY, true) as $item) {
            if ($item->isFile() && $item->lastModified() < $expired) {
                $this->privateFilesystem->delete($item->path());
            }
        }
    }
}
