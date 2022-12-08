<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\ScheduledTask;

use Shopware\Core\Content\ImportExport\Service\DeleteExpiredFilesService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @internal
 *
 * @package system-settings
 */
final class CleanupImportExportFileTaskHandler extends ScheduledTaskHandler
{
    private DeleteExpiredFilesService $deleteExpiredFilesService;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $repository,
        DeleteExpiredFilesService $deleteExpiredFilesService
    ) {
        parent::__construct($repository);

        $this->deleteExpiredFilesService = $deleteExpiredFilesService;
    }

    /**
     * @return iterable<class-string<ScheduledTask>>
     */
    public static function getHandledMessages(): iterable
    {
        yield CleanupImportExportFileTask::class;
    }

    public function run(): void
    {
        $this->deleteExpiredFilesService->deleteFiles(Context::createDefaultContext());
    }
}
