<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\ScheduledTask;

use Shopware\Core\Content\ImportExport\Service\DeleteExpiredFilesService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class CleanupImportExportFileTaskHandler extends ScheduledTaskHandler
{
    private DeleteExpiredFilesService $deleteExpiredFilesService;

    public function __construct(
        EntityRepositoryInterface $repository,
        DeleteExpiredFilesService $deleteExpiredFilesService
    ) {
        parent::__construct($repository);

        $this->deleteExpiredFilesService = $deleteExpiredFilesService;
    }

    public static function getHandledMessages(): iterable
    {
        return [CleanupImportExportFileTask::class];
    }

    public function run(): void
    {
        $this->deleteExpiredFilesService->deleteFiles(Context::createDefaultContext());
    }
}
