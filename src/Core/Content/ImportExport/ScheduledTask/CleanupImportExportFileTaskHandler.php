<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\ScheduledTask;

use Shopware\Core\Content\ImportExport\Service\DeleteExpiredFilesService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: CleanupImportExportFileTask::class)]
#[Package('system-settings')]

final class CleanupImportExportFileTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(
        EntityRepository $repository,
        private readonly DeleteExpiredFilesService $deleteExpiredFilesService
    ) {
        parent::__construct($repository);
    }

    public function run(): void
    {
        $this->deleteExpiredFilesService->deleteFiles(Context::createDefaultContext());
    }
}
